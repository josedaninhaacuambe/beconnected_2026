-- Stock Reservations table (gerida pelo Stock Service, não pelo Laravel)
CREATE TABLE IF NOT EXISTS stock_reservations (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    checkout_id      BIGINT NOT NULL,
    product_id       BIGINT UNSIGNED NOT NULL,
    quantity         INT NOT NULL,
    status           VARCHAR(32) NOT NULL DEFAULT 'reserved',
    expires_at       DATETIME NOT NULL,
    created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_checkout_id (checkout_id),
    INDEX idx_product_id  (product_id),
    INDEX idx_status      (status),
    INDEX idx_expires_at  (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
