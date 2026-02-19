USE erply_sync;

-- Main products table
CREATE TABLE IF NOT EXISTS from_erply_products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(255),
    code VARCHAR(100),
    price DECIMAL(10,2),
    quantity INT DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (product_id),
    INDEX (code)
);

-- Matrix products table
CREATE TABLE IF NOT EXISTS from_erply_matrix_products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id VARCHAR(50) NOT NULL UNIQUE,
    parent_id VARCHAR(50),
    name VARCHAR(255),
    code VARCHAR(100),
    price DECIMAL(10,2),
    quantity INT DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (product_id),
    INDEX (parent_id),
    INDEX (code)
);

-- Sync log table
CREATE TABLE IF NOT EXISTS erply_sync_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sync_type VARCHAR(50),
    records_processed INT,
    status VARCHAR(20),
    message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);