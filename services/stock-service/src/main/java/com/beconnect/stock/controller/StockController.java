package com.beconnect.stock.controller;

import com.beconnect.stock.service.StockService;
import com.beconnect.stock.service.StockService.*;
import lombok.RequiredArgsConstructor;
import org.springframework.beans.factory.annotation.Value;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.*;

import java.util.List;
import java.util.Map;

@RestController
@RequestMapping("/api/stock")
@RequiredArgsConstructor
public class StockController {

    private final StockService stockService;

    @Value("${app.internal-api-key}")
    private String internalApiKey;

    // ─── Validação interna ────────────────────────────────────────────────────
    private boolean isAuthorized(String key) {
        return internalApiKey.equals(key);
    }

    /** GET /api/stock/{productId} — consultar stock de um produto */
    @GetMapping("/{productId}")
    public ResponseEntity<?> getStock(
            @PathVariable Long productId,
            @RequestHeader(value = "X-Internal-Key", required = false) String key) {

        if (!isAuthorized(key))
            return ResponseEntity.status(401).body(Map.of("message", "Não autorizado."));

        StockInfo info = stockService.getStock(productId);
        return ResponseEntity.ok(Map.of(
            "productId", info.productId(),
            "available", info.available(),
            "price",     info.unitPrice(),
            "store_id",  info.storeId() != null ? info.storeId() : ""
        ));
    }

    /** POST /api/stock/reserve — reservar stock para checkout */
    @PostMapping("/reserve")
    public ResponseEntity<?> reserve(
            @RequestBody ReserveRequest req,
            @RequestHeader(value = "X-Internal-Key", required = false) String key) {

        if (!isAuthorized(key))
            return ResponseEntity.status(401).body(Map.of("message", "Não autorizado."));

        List<ReserveItem> items = req.items().stream()
            .map(i -> new ReserveItem(i.productId(), i.quantity()))
            .toList();

        ReservationResult result = stockService.reserve(req.checkoutId(), items);

        if (!result.success()) {
            return ResponseEntity.status(422).body(Map.of(
                "message",    result.errorMessage(),
                "checkoutId", req.checkoutId()
            ));
        }

        return ResponseEntity.ok(Map.of("message", "Stock reservado.", "checkoutId", req.checkoutId()));
    }

    /** POST /api/stock/release — liberta reserva (pagamento falhado) */
    @PostMapping("/release")
    public ResponseEntity<?> release(
            @RequestBody Map<String, Long> body,
            @RequestHeader(value = "X-Internal-Key", required = false) String key) {

        if (!isAuthorized(key))
            return ResponseEntity.status(401).body(Map.of("message", "Não autorizado."));

        stockService.release(body.get("checkoutId"));
        return ResponseEntity.ok(Map.of("message", "Reserva libertada."));
    }

    /** POST /api/stock/confirm — confirma reserva (pagamento aprovado) */
    @PostMapping("/confirm")
    public ResponseEntity<?> confirm(
            @RequestBody Map<String, Long> body,
            @RequestHeader(value = "X-Internal-Key", required = false) String key) {

        if (!isAuthorized(key))
            return ResponseEntity.status(401).body(Map.of("message", "Não autorizado."));

        stockService.confirm(body.get("checkoutId"));
        return ResponseEntity.ok(Map.of("message", "Stock confirmado."));
    }

    @GetMapping("/health")
    public ResponseEntity<?> health() {
        return ResponseEntity.ok(Map.of("status", "ok", "service", "stock-service"));
    }

    // ─── DTOs ─────────────────────────────────────────────────────────────────
    record ReserveRequest(Long checkoutId, List<ReserveItemDto> items) {}
    record ReserveItemDto(Long productId, int quantity) {}
}
