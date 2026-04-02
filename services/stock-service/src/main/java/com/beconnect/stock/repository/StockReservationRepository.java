package com.beconnect.stock.repository;

import com.beconnect.stock.model.StockReservation;
import org.springframework.data.jpa.repository.JpaRepository;
import org.springframework.data.jpa.repository.Modifying;
import org.springframework.data.jpa.repository.Query;

import java.time.LocalDateTime;
import java.util.List;

public interface StockReservationRepository extends JpaRepository<StockReservation, Long> {

    List<StockReservation> findByCheckoutIdAndStatus(Long checkoutId, String status);

    List<StockReservation> findByExpiresAtBeforeAndStatus(LocalDateTime threshold, String status);

    @Modifying
    @Query("UPDATE StockReservation r SET r.status = 'expired', r.updatedAt = :now WHERE r.expiresAt < :now AND r.status = 'reserved'")
    int expireOldReservations(LocalDateTime now);
}
