package com.beconnect.stock.model;

import jakarta.persistence.*;
import lombok.Data;
import lombok.NoArgsConstructor;

import java.time.LocalDateTime;

/**
 * Registo de reservas de stock associadas a um checkout.
 * Expiram automaticamente se o pagamento não for confirmado.
 */
@Entity
@Table(name = "stock_reservations",
       indexes = {
           @Index(name = "idx_checkout_id", columnList = "checkout_id"),
           @Index(name = "idx_product_id",  columnList = "product_id"),
           @Index(name = "idx_status",      columnList = "status"),
           @Index(name = "idx_expires_at",  columnList = "expires_at"),
       })
@Data
@NoArgsConstructor
public class StockReservation {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @Column(name = "checkout_id", nullable = false)
    private Long checkoutId;

    @Column(name = "product_id", nullable = false)
    private Long productId;

    @Column(nullable = false)
    private Integer quantity;

    @Column(nullable = false, length = 32)
    private String status = "reserved";   // reserved | confirmed | released | expired

    @Column(name = "expires_at", nullable = false)
    private LocalDateTime expiresAt;

    @Column(name = "created_at")
    private LocalDateTime createdAt = LocalDateTime.now();

    @Column(name = "updated_at")
    private LocalDateTime updatedAt = LocalDateTime.now();

    @PreUpdate
    public void onUpdate() {
        this.updatedAt = LocalDateTime.now();
    }
}
