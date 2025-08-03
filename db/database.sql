-- Library Management System Database Schema

-- Create database
CREATE DATABASE IF NOT EXISTS library_management;
USE library_management;

-- Users table for authentication and role management
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'librarian', 'member') NOT NULL DEFAULT 'member',
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE
);

-- Books table
CREATE TABLE books (
    id INT PRIMARY KEY AUTO_INCREMENT,
    isbn VARCHAR(20) UNIQUE NOT NULL,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(255) NOT NULL,
    publisher VARCHAR(255),
    publication_year INT,
    genre VARCHAR(100),
    description TEXT,
    total_copies INT NOT NULL DEFAULT 1,
    available_copies INT NOT NULL DEFAULT 1,
    location VARCHAR(100),
    price DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Customers table (for library members)
CREATE TABLE customers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    customer_code VARCHAR(20) UNIQUE NOT NULL,
    membership_type ENUM('student', 'faculty', 'public') NOT NULL DEFAULT 'public',
    membership_expiry DATE,
    max_books_allowed INT DEFAULT 3,
    fine_balance DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Library passes table
CREATE TABLE library_passes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NOT NULL,
    pass_number VARCHAR(20) UNIQUE NOT NULL,
    issue_date DATE NOT NULL,
    expiry_date DATE NOT NULL,
    status ENUM('active', 'expired', 'suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

-- Book issues table
CREATE TABLE book_issues (
    id INT PRIMARY KEY AUTO_INCREMENT,
    book_id INT NOT NULL,
    customer_id INT NOT NULL,
    issue_date DATE NOT NULL,
    due_date DATE NOT NULL,
    return_date DATE NULL,
    status ENUM('issued', 'returned', 'overdue') DEFAULT 'issued',
    issued_by INT NOT NULL,
    returned_to INT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (issued_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (returned_to) REFERENCES users(id) ON DELETE SET NULL
);

-- Reservations table
CREATE TABLE reservations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    book_id INT NOT NULL,
    customer_id INT NOT NULL,
    reservation_date DATE NOT NULL,
    expiry_date DATE NOT NULL,
    status ENUM('pending', 'fulfilled', 'expired', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

-- Fines table
CREATE TABLE fines (
    id INT PRIMARY KEY AUTO_INCREMENT,
    book_issue_id INT NOT NULL,
    customer_id INT NOT NULL,
    fine_type ENUM('late_return', 'damage', 'lost') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    description TEXT,
    status ENUM('pending', 'paid', 'waived') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (book_issue_id) REFERENCES book_issues(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

-- Insert default admin user
INSERT INTO users (username, email, password, role, first_name, last_name) 
VALUES ('admin', 'admin@library.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'System', 'Administrator');

-- Insert demo users
INSERT INTO users (username, email, password, role, first_name, last_name, phone, address) VALUES
('librarian', 'librarian@library.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'librarian', 'Sarah', 'Johnson', '555-0101', '123 Library St, City'),
('member', 'member@library.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'member', 'John', 'Doe', '555-0202', '456 Reader Ave, Town');

-- Insert demo customers
INSERT INTO customers (user_id, customer_code, membership_type, membership_expiry, max_books_allowed) VALUES
(2, 'CUST20240001', 'faculty', '2025-12-31', 5),
(3, 'CUST20240002', 'student', '2024-06-30', 3);

-- Insert sample books
INSERT INTO books (isbn, title, author, publisher, publication_year, genre, total_copies, available_copies, price) VALUES
('978-0-7475-3269-9', 'Harry Potter and the Philosopher''s Stone', 'J.K. Rowling', 'Bloomsbury', 1997, 'Fantasy', 5, 5, 29.99),
('978-0-7475-3849-3', 'Harry Potter and the Chamber of Secrets', 'J.K. Rowling', 'Bloomsbury', 1998, 'Fantasy', 3, 3, 29.99),
('978-0-7475-4215-5', 'Harry Potter and the Prisoner of Azkaban', 'J.K. Rowling', 'Bloomsbury', 1999, 'Fantasy', 4, 4, 29.99),
('978-0-7475-4624-5', 'Harry Potter and the Goblet of Fire', 'J.K. Rowling', 'Bloomsbury', 2000, 'Fantasy', 3, 3, 34.99),
('978-0-7475-5100-6', 'Harry Potter and the Order of the Phoenix', 'J.K. Rowling', 'Bloomsbury', 2003, 'Fantasy', 2, 2, 34.99),
('978-0-7475-8108-9', 'Harry Potter and the Half-Blood Prince', 'J.K. Rowling', 'Bloomsbury', 2005, 'Fantasy', 2, 2, 34.99),
('978-0-7475-8109-6', 'Harry Potter and the Deathly Hallows', 'J.K. Rowling', 'Bloomsbury', 2007, 'Fantasy', 3, 3, 34.99),
('978-0-316-06529-2', 'The Da Vinci Code', 'Dan Brown', 'Doubleday', 2003, 'Thriller', 4, 4, 24.99),
('978-0-316-06530-8', 'Angels & Demons', 'Dan Brown', 'Pocket Books', 2000, 'Thriller', 3, 3, 24.99),
('978-0-316-06531-5', 'Digital Fortress', 'Dan Brown', 'St. Martin''s Press', 1998, 'Thriller', 2, 2, 24.99); 