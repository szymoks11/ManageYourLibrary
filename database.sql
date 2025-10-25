-- ============================================
-- LIBRARY MANAGEMENT SYSTEM DATABASE - MySQL
-- ============================================

-- Create Database
CREATE DATABASE IF NOT EXISTS library_management;
USE library_management;

-- Set charset
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================
-- Drop tables if exist (for clean setup)
-- ============================================
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS fines;
DROP TABLE IF EXISTS reservations;
DROP TABLE IF EXISTS loans;
DROP TABLE IF EXISTS books;
DROP TABLE IF EXISTS genres;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================
-- 1. USERS TABLE
-- ============================================
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'worker', 'client') NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_role (role),
    INDEX idx_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 2. GENRES TABLE
-- ============================================
CREATE TABLE genres (
    genre_id INT AUTO_INCREMENT PRIMARY KEY,
    genre_name VARCHAR(50) UNIQUE NOT NULL,
    description TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 3. BOOKS TABLE
-- ============================================
CREATE TABLE books (
    book_id INT AUTO_INCREMENT PRIMARY KEY,
    isbn VARCHAR(13) UNIQUE,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(255) NOT NULL,
    publisher VARCHAR(100),
    publication_year INT,
    genre_id INT,
    total_copies INT NOT NULL DEFAULT 1,
    available_copies INT NOT NULL DEFAULT 1,
    shelf_location VARCHAR(50),
    description TEXT,
    cover_image_url VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (genre_id) REFERENCES genres(genre_id) ON DELETE SET NULL,
    CONSTRAINT check_copies CHECK (available_copies <= total_copies),
    CONSTRAINT check_total_copies CHECK (total_copies >= 0),
    CONSTRAINT check_available_copies CHECK (available_copies >= 0),
    INDEX idx_books_title (title),
    INDEX idx_books_author (author),
    INDEX idx_books_isbn (isbn),
    INDEX idx_books_genre (genre_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 4. LOANS TABLE
-- ============================================
CREATE TABLE loans (
    loan_id INT AUTO_INCREMENT PRIMARY KEY,
    book_id INT NOT NULL,
    client_id INT NOT NULL,
    worker_id INT NOT NULL,
    loan_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    due_date DATE NOT NULL,
    return_date TIMESTAMP NULL,
    status ENUM('active', 'returned', 'overdue', 'lost') NOT NULL DEFAULT 'active',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (book_id) REFERENCES books(book_id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (worker_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_loans_client (client_id),
    INDEX idx_loans_book (book_id),
    INDEX idx_loans_status (status),
    INDEX idx_loans_due_date (due_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 5. RESERVATIONS TABLE
-- ============================================
CREATE TABLE reservations (
    reservation_id INT AUTO_INCREMENT PRIMARY KEY,
    book_id INT NOT NULL,
    client_id INT NOT NULL,
    reservation_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'fulfilled', 'cancelled', 'expired') DEFAULT 'pending',
    expiry_date TIMESTAMP NULL,
    notes TEXT,
    FOREIGN KEY (book_id) REFERENCES books(book_id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_reservations_client (client_id),
    INDEX idx_reservations_book (book_id),
    INDEX idx_reservations_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 6. FINES TABLE
-- ============================================
CREATE TABLE fines (
    fine_id INT AUTO_INCREMENT PRIMARY KEY,
    loan_id INT NOT NULL,
    client_id INT NOT NULL,
    fine_amount DECIMAL(10, 2) NOT NULL,
    reason VARCHAR(100),
    is_paid TINYINT(1) DEFAULT 0,
    payment_date TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (loan_id) REFERENCES loans(loan_id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT check_fine_amount CHECK (fine_amount >= 0),
    INDEX idx_fines_client (client_id),
    INDEX idx_fines_paid (is_paid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 7. AUDIT LOGS TABLE
-- ============================================
CREATE TABLE audit_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(50),
    record_id INT,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_audit_user (user_id),
    INDEX idx_audit_action (action),
    INDEX idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TRIGGERS
-- ============================================

-- Trigger to decrease available copies when book is loaned
DELIMITER //
CREATE TRIGGER after_loan_insert
AFTER INSERT ON loans
FOR EACH ROW
BEGIN
    IF NEW.status = 'active' THEN
        UPDATE books 
        SET available_copies = available_copies - 1 
        WHERE book_id = NEW.book_id;
    END IF;
END//
DELIMITER ;

-- Trigger to increase available copies when book is returned
DELIMITER //
CREATE TRIGGER after_loan_update
AFTER UPDATE ON loans
FOR EACH ROW
BEGIN
    IF OLD.status = 'active' AND NEW.status = 'returned' THEN
        UPDATE books 
        SET available_copies = available_copies + 1 
        WHERE book_id = NEW.book_id;
    END IF;
END//
DELIMITER ;

-- Trigger to update loan status to overdue
DELIMITER //
CREATE TRIGGER before_loan_check
BEFORE INSERT ON loans
FOR EACH ROW
BEGIN
    IF NEW.due_date < CURDATE() AND NEW.status = 'active' THEN
        SET NEW.status = 'overdue';
    END IF;
END//
DELIMITER ;

-- ============================================
-- VIEWS
-- ============================================

-- View: Available Books
CREATE OR REPLACE VIEW available_books AS
SELECT 
    b.book_id,
    b.isbn,
    b.title,
    b.author,
    b.publisher,
    b.publication_year,
    g.genre_name,
    b.available_copies,
    b.total_copies,
    b.shelf_location,
    b.description
FROM books b
LEFT JOIN genres g ON b.genre_id = g.genre_id
WHERE b.available_copies > 0;

-- View: Active Loans with Details
CREATE OR REPLACE VIEW active_loans_view AS
SELECT 
    l.loan_id,
    b.title AS book_title,
    b.author AS book_author,
    b.isbn,
    c.full_name AS client_name,
    c.email AS client_email,
    c.phone AS client_phone,
    w.full_name AS worker_name,
    l.loan_date,
    l.due_date,
    l.status,
    DATEDIFF(CURDATE(), l.due_date) AS days_overdue
FROM loans l
JOIN books b ON l.book_id = b.book_id
JOIN users c ON l.client_id = c.user_id
JOIN users w ON l.worker_id = w.user_id
WHERE l.status IN ('active', 'overdue');

-- View: Overdue Loans
CREATE OR REPLACE VIEW overdue_loans_view AS
SELECT 
    l.loan_id,
    b.title,
    b.isbn,
    c.full_name AS client_name,
    c.email,
    c.phone,
    l.due_date,
    DATEDIFF(CURDATE(), l.due_date) AS days_overdue
FROM loans l
JOIN books b ON l.book_id = b.book_id
JOIN users c ON l.client_id = c.user_id
WHERE l.status IN ('active', 'overdue') 
  AND l.due_date < CURDATE();

-- View: Client Loan History
CREATE OR REPLACE VIEW client_loan_history AS
SELECT 
    l.loan_id,
    l.client_id,
    u.full_name AS client_name,
    b.title AS book_title,
    b.author,
    l.loan_date,
    l.due_date,
    l.return_date,
    l.status,
    COALESCE(f.fine_amount, 0) AS fine_amount,
    COALESCE(f.is_paid, 0) AS fine_paid
FROM loans l
JOIN users u ON l.client_id = u.user_id
JOIN books b ON l.book_id = b.book_id
LEFT JOIN fines f ON l.loan_id = f.loan_id
ORDER BY l.loan_date DESC;

-- View: Books Statistics
CREATE OR REPLACE VIEW books_statistics AS
SELECT 
    g.genre_name,
    COUNT(b.book_id) AS total_books,
    SUM(b.total_copies) AS total_copies,
    SUM(b.available_copies) AS available_copies,
    SUM(b.total_copies - b.available_copies) AS loaned_copies
FROM books b
LEFT JOIN genres g ON b.genre_id = g.genre_id
GROUP BY g.genre_name;

-- ============================================
-- STORED PROCEDURES AND FUNCTIONS
-- ============================================

-- Function to calculate fine amount
DELIMITER //
CREATE FUNCTION calculate_fine(p_loan_id INT)
RETURNS DECIMAL(10,2)
DETERMINISTIC
BEGIN
    DECLARE v_due_date DATE;
    DECLARE v_return_date DATE;
    DECLARE v_days_overdue INT;
    DECLARE v_fine_per_day DECIMAL(10,2) DEFAULT 0.50;
    DECLARE v_fine_amount DECIMAL(10,2);
    
    SELECT due_date, return_date INTO v_due_date, v_return_date
    FROM loans WHERE loan_id = p_loan_id;
    
    IF v_return_date IS NULL THEN
        SET v_return_date = CURDATE();
    END IF;
    
    SET v_days_overdue = DATEDIFF(v_return_date, v_due_date);
    
    IF v_days_overdue > 0 THEN
        SET v_fine_amount = v_days_overdue * v_fine_per_day;
    ELSE
        SET v_fine_amount = 0;
    END IF;
    
    RETURN v_fine_amount;
END//
DELIMITER ;

-- Procedure to loan a book
DELIMITER //
CREATE PROCEDURE loan_book(
    IN p_book_id INT,
    IN p_client_id INT,
    IN p_worker_id INT,
    IN p_loan_days INT
)
BEGIN
    DECLARE v_available INT;
    DECLARE v_due_date DATE;
    
    -- Check if book is available
    SELECT available_copies INTO v_available
    FROM books WHERE book_id = p_book_id;
    
    IF v_available > 0 THEN
        SET v_due_date = DATE_ADD(CURDATE(), INTERVAL p_loan_days DAY);
        
        INSERT INTO loans (book_id, client_id, worker_id, due_date, status)
        VALUES (p_book_id, p_client_id, p_worker_id, v_due_date, 'active');
        
        SELECT 'Book loaned successfully' AS message, LAST_INSERT_ID() AS loan_id;
    ELSE
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Book is not available';
    END IF;
END//
DELIMITER ;

-- Procedure to return a book
DELIMITER //
CREATE PROCEDURE return_book(IN p_loan_id INT)
BEGIN
    DECLARE v_status VARCHAR(20);
    DECLARE v_fine DECIMAL(10,2);
    DECLARE v_client_id INT;
    
    -- Get loan details
    SELECT status, client_id INTO v_status, v_client_id
    FROM loans WHERE loan_id = p_loan_id;
    
    IF v_status = 'returned' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Book already returned';
    END IF;
    
    -- Calculate fine
    SET v_fine = calculate_fine(p_loan_id);
    
    -- Update loan
    UPDATE loans 
    SET status = 'returned', return_date = NOW()
    WHERE loan_id = p_loan_id;
    
    -- Insert fine if applicable
    IF v_fine > 0 THEN
        INSERT INTO fines (loan_id, client_id, fine_amount, reason)
        VALUES (p_loan_id, v_client_id, v_fine, 'Late return');
    END IF;
    
    SELECT 'Book returned successfully' AS message, v_fine AS fine_amount;
END//
DELIMITER ;

-- Procedure to search books
DELIMITER //
CREATE PROCEDURE search_books(IN p_search_term VARCHAR(255))
BEGIN
    SELECT 
        b.book_id,
        b.isbn,
        b.title,
        b.author,
        b.publisher,
        b.publication_year,
        g.genre_name,
        b.available_copies,
        b.total_copies,
        b.shelf_location
    FROM books b
    LEFT JOIN genres g ON b.genre_id = g.genre_id
    WHERE b.title LIKE CONCAT('%', p_search_term, '%')
       OR b.author LIKE CONCAT('%', p_search_term, '%')
       OR b.isbn LIKE CONCAT('%', p_search_term, '%')
       OR g.genre_name LIKE CONCAT('%', p_search_term, '%');
END//
DELIMITER ;

-- Procedure to get user loan history
DELIMITER //
CREATE PROCEDURE get_user_loans(IN p_user_id INT)
BEGIN
    SELECT 
        l.loan_id,
        b.title,
        b.author,
        l.loan_date,
        l.due_date,
        l.return_date,
        l.status,
        COALESCE(f.fine_amount, 0) AS fine_amount,
        COALESCE(f.is_paid, 0) AS fine_paid
    FROM loans l
    JOIN books b ON l.book_id = b.book_id
    LEFT JOIN fines f ON l.loan_id = f.loan_id
    WHERE l.client_id = p_user_id
    ORDER BY l.loan_date DESC;
END//
DELIMITER ;

-- Procedure to get overdue loans
DELIMITER //
CREATE PROCEDURE get_overdue_loans()
BEGIN
    SELECT * FROM overdue_loans_view;
END//
DELIMITER ;

-- Procedure to update user role (Admin only)
DELIMITER //
CREATE PROCEDURE update_user_role(
    IN p_user_id INT,
    IN p_new_role VARCHAR(20)
)
BEGIN
    UPDATE users 
    SET role = p_new_role
    WHERE user_id = p_user_id;
    
    SELECT 'Role updated successfully' AS message;
END//
DELIMITER ;

-- ============================================
-- SAMPLE DATA
-- ============================================

-- Insert Genres
INSERT INTO genres (genre_name, description) VALUES
('Fiction', 'Literary works of fiction'),
('Non-Fiction', 'Factual and informative works'),
('Science Fiction', 'Speculative fiction based on science'),
('Mystery', 'Crime and detective fiction'),
('Biography', 'Life stories of real people'),
('History', 'Historical accounts and analysis'),
('Technology', 'Books about technology and computing'),
('Self-Help', 'Personal development books'),
('Fantasy', 'Fantasy and magical worlds'),
('Romance', 'Romantic fiction');

-- Insert Sample Users
-- Note: In production, use properly hashed passwords
-- These are example hashes for 'password123'
INSERT INTO users (username, email, password_hash, full_name, role, phone, address) VALUES
('admin', 'admin@library.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin', '555-0001', '123 Admin Street'),
('john_worker', 'john@library.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'John Smith', 'worker', '555-0002', '456 Worker Avenue'),
('jane_worker', 'jane@library.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Jane Doe', 'worker', '555-0003', '789 Worker Boulevard'),
('alice_client', 'alice@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Alice Johnson', 'client', '555-0004', '321 Client Road'),
('bob_client', 'bob@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Bob Williams', 'client', '555-0005', '654 Client Lane'),
('carol_client', 'carol@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Carol Brown', 'client', '555-0006', '987 Client Circle');

-- Insert Sample Books
INSERT INTO books (isbn, title, author, publisher, publication_year, genre_id, total_copies, available_copies, shelf_location, description) VALUES
('9780141439518', 'Pride and Prejudice', 'Jane Austen', 'Penguin Classics', 1813, 1, 3, 3, 'A-101', 'A classic romance novel'),
('9780743273565', 'The Great Gatsby', 'F. Scott Fitzgerald', 'Scribner', 1925, 1, 2, 2, 'A-102', 'The story of the mysteriously wealthy Jay Gatsby'),
('9780451524935', '1984', 'George Orwell', 'Signet Classic', 1949, 3, 4, 4, 'B-201', 'Dystopian social science fiction'),
('9780062315007', 'The Alchemist', 'Paulo Coelho', 'HarperOne', 1988, 1, 3, 3, 'A-103', 'A philosophical book about following your dreams'),
('9780544003415', 'The Lord of the Rings', 'J.R.R. Tolkien', 'Mariner Books', 1954, 9, 2, 2, 'C-301', 'Epic high-fantasy novel'),
('9780062073556', 'Sapiens', 'Yuval Noah Harari', 'Harper', 2011, 2, 5, 5, 'D-401', 'A brief history of humankind'),
('9780735619678', 'A Game of Thrones', 'George R.R. Martin', 'Bantam', 1996, 9, 3, 3, 'C-302', 'First book in A Song of Ice and Fire'),
('9780590353427', 'Harry Potter and the Sorcerer\'s Stone', 'J.K. Rowling', 'Scholastic', 1997, 9, 5, 5, 'C-303', 'The beginning of the Harry Potter series'),
('9780316769488', 'The Catcher in the Rye', 'J.D. Salinger', 'Little, Brown', 1951, 1, 2, 2, 'A-104', 'Story of teenage rebellion'),
('9780061120084', 'To Kill a Mockingbird', 'Harper Lee', 'Harper Perennial', 1960, 1, 4, 4, 'A-105', 'A novel about racial injustice'),
('9780307387899', 'The Road', 'Cormac McCarthy', 'Vintage', 2006, 1, 2, 2, 'A-106', 'Post-apocalyptic novel'),
('9780553381689', 'A Brief History of Time', 'Stephen Hawking', 'Bantam', 1988, 7, 3, 3, 'D-402', 'Popular science book on cosmology'),
('9780618640157', 'The Hobbit', 'J.R.R. Tolkien', 'Mariner Books', 1937, 9, 4, 4, 'C-304', 'Fantasy novel and prelude to LOTR'),
('9780142424179', 'The Fault in Our Stars', 'John Green', 'Penguin', 2012, 10, 3, 3, 'A-107', 'Young adult romance'),
('9780743477109', 'The Da Vinci Code', 'Dan Brown', 'Anchor', 2003, 4, 2, 2, 'B-202', 'Mystery thriller novel');

-- Insert Sample Loans
INSERT INTO loans (book_id, client_id, worker_id, loan_date, due_date, status) VALUES
(1, 4, 2, DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_ADD(CURDATE(), INTERVAL 4 DAY), 'active'),
(3, 5, 2, DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_ADD(CURDATE(), INTERVAL 9 DAY), 'active'),
(8, 6, 3, DATE_SUB(NOW(), INTERVAL 20 DAY), DATE_SUB(CURDATE(), INTERVAL 6 DAY), 'overdue');

-- Insert Sample Reservations
INSERT INTO reservations (book_id, client_id, status, expiry_date) VALUES
(2, 4, 'pending', DATE_ADD(NOW(), INTERVAL 7 DAY)),
(5, 5, 'pending', DATE_ADD(NOW(), INTERVAL 5 DAY));

-- Insert Sample Fine
INSERT INTO fines (loan_id, client_id, fine_amount, reason, is_paid) VALUES
(3, 6, 3.00, 'Late return', 0);

-- ============================================
-- USEFUL QUERIES FOR TESTING
-- ============================================

-- View all available books
-- SELECT * FROM available_books;

-- View all active loans
-- SELECT * FROM active_loans_view;

-- View overdue loans
-- SELECT * FROM overdue_loans_view;

-- Search for books
-- CALL search_books('Harry');

-- Get user loan history
-- CALL get_user_loans(4);

-- Loan a book (book_id, client_id, worker_id, days)
-- CALL loan_book(2, 4, 2, 14);

-- Return a book
-- CALL return_book(1);

-- Calculate fine for a loan
-- SELECT calculate_fine(3) AS fine_amount;

-- Get books statistics
-- SELECT * FROM books_statistics;