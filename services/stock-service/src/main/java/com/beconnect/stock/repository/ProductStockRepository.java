package com.beconnect.stock.repository;

import com.beconnect.stock.model.ProductStock;
import org.springframework.data.jpa.repository.JpaRepository;
import org.springframework.data.jpa.repository.Lock;
import org.springframework.data.jpa.repository.Query;

import jakarta.persistence.LockModeType;
import java.util.List;
import java.util.Optional;

public interface ProductStockRepository extends JpaRepository<ProductStock, Long> {

    Optional<ProductStock> findByProductId(Long productId);

    /** Lock pessimista FOR UPDATE — garante exclusividade na reserva */
    @Lock(LockModeType.PESSIMISTIC_WRITE)
    @Query("SELECT s FROM ProductStock s WHERE s.productId = :productId")
    Optional<ProductStock> findByProductIdForUpdate(Long productId);

    @Lock(LockModeType.PESSIMISTIC_WRITE)
    @Query("SELECT s FROM ProductStock s WHERE s.productId IN :productIds ORDER BY s.productId ASC")
    List<ProductStock> findAllByProductIdInForUpdate(List<Long> productIds);
}
