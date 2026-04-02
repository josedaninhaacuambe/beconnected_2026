package com.beconnect.stock.model;

import jakarta.persistence.*;
import lombok.Data;
import lombok.NoArgsConstructor;

import java.math.BigDecimal;
import java.time.LocalDateTime;

/**
 * Mapeamento da tabela product_stocks (gerida pelo Laravel).
 * O Stock Service só lê e actualiza — nunca altera o schema.
 */
@Entity
@Table(name = "product_stocks")
@Data
@NoArgsConstructor
public class ProductStock {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @Column(name = "product_id", nullable = false, unique = true)
    private Long productId;

    @Column(nullable = false)
    private Integer quantity;

    @Column(name = "reserved_quantity", nullable = false)
    private Integer reservedQuantity = 0;

    @Column(name = "created_at")
    private LocalDateTime createdAt;

    @Column(name = "updated_at")
    private LocalDateTime updatedAt;

    @PreUpdate
    public void onUpdate() {
        this.updatedAt = LocalDateTime.now();
    }

    /** Stock disponível real = total − reservado */
    @Transient
    public int getAvailable() {
        return Math.max(0, quantity - reservedQuantity);
    }
}
