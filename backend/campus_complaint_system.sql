
CREATE DATABASE IF NOT EXISTS campus_complaint_system;
USE campus_complaint_system;

CREATE TABLE users (
  user_id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  phone VARCHAR(20),
  password VARCHAR(255) NOT NULL,
  role ENUM('admin','student','staff') NOT NULL,
  status ENUM('active','inactive') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE buildings(
  building_id INT AUTO_INCREMENT PRIMARY KEY,
  building_name VARCHAR(100) NOT NULL UNIQUE,
  description VARCHAR(255)
);

CREATE TABLE complaint_categories(
  category_id INT AUTO_INCREMENT PRIMARY KEY,
  category_name VARCHAR(100) NOT NULL UNIQUE,
  description VARCHAR(255)
);

CREATE TABLE complaint_status(
  status_id INT AUTO_INCREMENT PRIMARY KEY,
  status_name VARCHAR(50) NOT NULL UNIQUE
);

CREATE TABLE students(
  student_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL UNIQUE,
  student_number VARCHAR(50) UNIQUE,
  department VARCHAR(100),
  semester VARCHAR(30),
  building_id INT,
  room_no VARCHAR(20),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  FOREIGN KEY(building_id) REFERENCES buildings(building_id) ON DELETE SET NULL
);

CREATE TABLE maintenance_staff(
  staff_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL UNIQUE,
  employee_id VARCHAR(50) UNIQUE,
  designation VARCHAR(100),
  specialization VARCHAR(100),
  phone VARCHAR(20),
  availability ENUM('available','busy','offline') DEFAULT 'available',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

CREATE TABLE complaints(
 complaint_id INT AUTO_INCREMENT PRIMARY KEY,
 student_id INT NOT NULL,
 category_id INT NOT NULL,
 building_id INT NOT NULL,
 status_id INT NOT NULL DEFAULT 1,
 title VARCHAR(200) NOT NULL,
 description TEXT NOT NULL,
 image VARCHAR(255),
 priority ENUM('Low','Medium','High') DEFAULT 'Medium',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 resolved_at DATETIME NULL,
 FOREIGN KEY(student_id) REFERENCES students(student_id),
 FOREIGN KEY(category_id) REFERENCES complaint_categories(category_id),
 FOREIGN KEY(building_id) REFERENCES buildings(building_id),
 FOREIGN KEY(status_id) REFERENCES complaint_status(status_id),
 INDEX(student_id),INDEX(category_id),INDEX(status_id)
);

CREATE TABLE assignments(
 assignment_id INT AUTO_INCREMENT PRIMARY KEY,
 complaint_id INT NOT NULL,
 staff_id INT NOT NULL,
 assigned_by INT NOT NULL,
 assigned_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 accepted_date DATETIME NULL,
 completed_date DATETIME NULL,
 repair_notes TEXT,
 repair_image VARCHAR(255),
 assignment_status ENUM('Assigned','Accepted','Rejected','Completed') DEFAULT 'Assigned',
 FOREIGN KEY(complaint_id) REFERENCES complaints(complaint_id) ON DELETE CASCADE,
 FOREIGN KEY(staff_id) REFERENCES maintenance_staff(staff_id),
 FOREIGN KEY(assigned_by) REFERENCES users(user_id)
);

CREATE TABLE complaint_updates(
 update_id INT AUTO_INCREMENT PRIMARY KEY,
 complaint_id INT NOT NULL,
 staff_id INT NOT NULL,
 status_id INT NOT NULL,
 progress_note TEXT,
 progress_image VARCHAR(255),
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(complaint_id) REFERENCES complaints(complaint_id) ON DELETE CASCADE,
 FOREIGN KEY(staff_id) REFERENCES maintenance_staff(staff_id),
 FOREIGN KEY(status_id) REFERENCES complaint_status(status_id)
);

CREATE TABLE feedback(
 feedback_id INT AUTO_INCREMENT PRIMARY KEY,
 complaint_id INT NOT NULL UNIQUE,
 student_id INT NOT NULL,
 rating TINYINT CHECK(rating BETWEEN 1 AND 5),
 comments TEXT,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(complaint_id) REFERENCES complaints(complaint_id) ON DELETE CASCADE,
 FOREIGN KEY(student_id) REFERENCES students(student_id)
);

CREATE TABLE notifications(
 notification_id INT AUTO_INCREMENT PRIMARY KEY,
 user_id INT NOT NULL,
 title VARCHAR(200),
 message TEXT,
 is_read BOOLEAN DEFAULT FALSE,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

CREATE TABLE admins(
 admin_id INT AUTO_INCREMENT PRIMARY KEY,
 user_id INT NOT NULL UNIQUE,
 designation VARCHAR(100),
 FOREIGN KEY(user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

INSERT INTO buildings(building_name) VALUES
('Academic Building'),('Library'),('Hostel'),('Cafeteria'),
('Laboratory'),('Auditorium'),('Playground');

INSERT INTO complaint_categories(category_name) VALUES
('Electrical'),('Plumbing'),('Internet/Wi-Fi'),
('Classroom Equipment'),('Projector'),('Fan'),
('AC'),('Water Supply'),('Furniture'),
('Cleanliness'),('Hostel Issues'),('Others');

INSERT INTO complaint_status(status_name) VALUES
('Pending'),('Assigned'),('Accepted'),
('In Progress'),('Resolved'),('Rejected'),('Closed');

INSERT INTO users(name,email,phone,password,role)
VALUES('System Admin','admin@campus.edu','0123456789',
'$2y$10$replace_with_hash','admin');

INSERT INTO admins(user_id,designation)
VALUES(1,'System Administrator');
