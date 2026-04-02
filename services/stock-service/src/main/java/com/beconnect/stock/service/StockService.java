package com.beconnect.stock.service;

import com.beconnect.stock.model.ProductStock;
import com.beconnect.stock.model.StockReservation;
import com.beconnect.stock.repository.ProductStockRepository;
import com.beconnect.stock.repository.StockReservationRepository;
import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import org.springframework.beans.factory.annotation.Value;
import org.springframework.data.redis.core.RedisTemplate;
import org.springframework.scheduling.annotation.Scheduled;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Isolation;
import org.springframework.transaction.annotation.Transactional;

import java.time.LocalDateTime;
import java.util.*;
import java.util.stream.Collectors;

@Service
@RequiredArgsConstructor
@Slf4j
public class StockService {

    private final ProductStockRepository      stockRepo;
    private final StockReservationRepository  reservationRepo;
    private final RedisTemplate<String, String> redis;

    @Value("${app.stock.reservation-ttl-minutes:15}")
    private int reservationTtlMinutes;

    // ─── Consulta de stock (com cache Redis 30s) ──────────────────────────────
    public StockInfo getStock(Long productId) {
        String cacheKey = "stock:" + productId;
        String cached   = redis.opsForValue().get(cacheKey);

        if (cached != null) {
            String[] parts    = cached.split(":");
            int available     = Integer.parseInt(parts[0]);
            String priceStr   = parts.length > 1 ? parts[1] : "0";
            String storeId    = parts.length > 2 ? parts[2] : null;
            return new StockInfo(productId, available, new java.math.BigDecimal(priceStr), storeId);
        }

        return stockRepo.findByProductId(productId)
            .map(s -> {
                // Buscar preço e storeId do produto (query separada)
                StockInfo info = buildStockInfo(s);
                redis.opsForValue().set(cacheKey, info.available() + ":" + info.unitPrice() + ":" + info.storeId(),
                    java.time.Duration.ofSeconds(30));
                return info;
            })
            .orElse(new StockInfo(productId, 0, java.math.BigDecimal.ZERO, null));
    }

    private StockInfo buildStockInfo(ProductStock s) {
        // Consulta simplificada — em produção faz JOIN com products
        return new StockInfo(s.getProductId(), s.getAvailable(), java.math.BigDecimal.ZERO, null);
    }

    // ─── Reservar stock (lock pessimista, ordem fixa de productId) ────────────
    @Transactional(isolation = Isolation.READ_COMMITTED)
    public ReservationResult reserve(Long checkoutId, List<ReserveItem> items) {
        // Ordena por productId crescente — previne deadlocks entre transações concorrentes
        List<Long> productIds = items.stream()
            .map(ReserveItem::productId)
            .sorted()
            .collect(Collectors.toList());

        Map<Long, Integer> requestedMap = items.stream()
            .collect(Collectors.toMap(ReserveItem::productId, ReserveItem::quantity));

        // Bloqueia todos os registos de uma vez (FOR UPDATE)
        List<ProductStock> stocks = stockRepo.findAllByProductIdInForUpdate(productIds);

        if (stocks.size() != productIds.size()) {
            Set<Long> found    = stocks.stream().map(ProductStock::getProductId).collect(Collectors.toSet());
            Set<Long> missing  = new HashSet<>(productIds);
            missing.removeAll(found);
            return ReservationResult.failure("Produto(s) não encontrado(s): " + missing);
        }

        // Verifica disponibilidade antes de modificar
        for (ProductStock stock : stocks) {
            int requested = requestedMap.get(stock.getProductId());
            if (stock.getAvailable() < requested) {
                return ReservationResult.failure(
                    "Stock insuficiente para produto " + stock.getProductId() +
                    ". Disponível: " + stock.getAvailable() + ", solicitado: " + requested
                );
            }
        }

        // Decrementa stock disponível e cria reservas
        LocalDateTime expiresAt = LocalDateTime.now().plusMinutes(reservationTtlMinutes);
        List<StockReservation> reservations = new ArrayList<>();

        for (ProductStock stock : stocks) {
            int qty = requestedMap.get(stock.getProductId());
            stock.setReservedQuantity(stock.getReservedQuantity() + qty);

            StockReservation res = new StockReservation();
            res.setCheckoutId(checkoutId);
            res.setProductId(stock.getProductId());
            res.setQuantity(qty);
            res.setStatus("reserved");
            res.setExpiresAt(expiresAt);
            reservations.add(res);

            // Invalida cache
            redis.delete("stock:" + stock.getProductId());
        }

        stockRepo.saveAll(stocks);
        reservationRepo.saveAll(reservations);

        log.info("Stock reserved: checkoutId={} products={}", checkoutId, productIds);
        return ReservationResult.success(checkoutId);
    }

    // ─── Liberta reserva (checkout cancelado ou pagamento falhado) ────────────
    @Transactional
    public void release(Long checkoutId) {
        List<StockReservation> reservations = reservationRepo.findByCheckoutIdAndStatus(checkoutId, "reserved");

        if (reservations.isEmpty()) {
            log.warn("No active reservations to release for checkoutId={}", checkoutId);
            return;
        }

        List<Long> productIds = reservations.stream()
            .map(StockReservation::getProductId)
            .sorted()
            .collect(Collectors.toList());

        List<ProductStock> stocks = stockRepo.findAllByProductIdInForUpdate(productIds);
        Map<Long, Integer> releaseMap = reservations.stream()
            .collect(Collectors.toMap(StockReservation::getProductId, StockReservation::getQuantity));

        for (ProductStock stock : stocks) {
            int qty = releaseMap.getOrDefault(stock.getProductId(), 0);
            stock.setReservedQuantity(Math.max(0, stock.getReservedQuantity() - qty));
            redis.delete("stock:" + stock.getProductId());
        }

        stockRepo.saveAll(stocks);
        reservations.forEach(r -> r.setStatus("released"));
        reservationRepo.saveAll(reservations);

        log.info("Stock released: checkoutId={}", checkoutId);
    }

    // ─── Confirma reserva (pagamento aprovado → desconta stock real) ──────────
    @Transactional
    public void confirm(Long checkoutId) {
        List<StockReservation> reservations = reservationRepo.findByCheckoutIdAndStatus(checkoutId, "reserved");

        List<Long> productIds = reservations.stream()
            .map(StockReservation::getProductId).sorted().collect(Collectors.toList());

        List<ProductStock> stocks = stockRepo.findAllByProductIdInForUpdate(productIds);
        Map<Long, Integer> confirmMap = reservations.stream()
            .collect(Collectors.toMap(StockReservation::getProductId, StockReservation::getQuantity));

        for (ProductStock stock : stocks) {
            int qty = confirmMap.getOrDefault(stock.getProductId(), 0);
            stock.setQuantity(Math.max(0, stock.getQuantity() - qty));
            stock.setReservedQuantity(Math.max(0, stock.getReservedQuantity() - qty));
            redis.delete("stock:" + stock.getProductId());
        }

        stockRepo.saveAll(stocks);
        reservations.forEach(r -> r.setStatus("confirmed"));
        reservationRepo.saveAll(reservations);

        log.info("Stock confirmed: checkoutId={}", checkoutId);
    }

    // ─── Job: expirar reservas antigas (a cada 5 minutos) ────────────────────
    @Scheduled(fixedRate = 300_000)
    @Transactional
    public void expireStaleReservations() {
        LocalDateTime now     = LocalDateTime.now();
        List<StockReservation> expired = reservationRepo.findByExpiresAtBeforeAndStatus(now, "reserved");

        if (expired.isEmpty()) return;

        log.info("Expiring {} stale reservations", expired.size());

        // Agrupa por produto e liberta
        Map<Long, Integer> toRelease = expired.stream()
            .collect(Collectors.groupingBy(StockReservation::getProductId,
                Collectors.summingInt(StockReservation::getQuantity)));

        List<ProductStock> stocks = stockRepo.findAllByProductIdInForUpdate(
            toRelease.keySet().stream().sorted().collect(Collectors.toList()));

        for (ProductStock stock : stocks) {
            int qty = toRelease.getOrDefault(stock.getProductId(), 0);
            stock.setReservedQuantity(Math.max(0, stock.getReservedQuantity() - qty));
            redis.delete("stock:" + stock.getProductId());
        }

        stockRepo.saveAll(stocks);
        reservationRepo.expireOldReservations(now);
    }

    // ─── Records / DTOs ───────────────────────────────────────────────────────
    public record StockInfo(Long productId, int available, java.math.BigDecimal unitPrice, String storeId) {}
    public record ReserveItem(Long productId, int quantity) {}
    public record ReservationResult(boolean success, Long checkoutId, String errorMessage) {
        static ReservationResult success(Long id)    { return new ReservationResult(true,  id,   null); }
        static ReservationResult failure(String msg) { return new ReservationResult(false, null, msg);  }
    }
}
