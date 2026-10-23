-- ==========================================================
-- StudentHub: Practical 11 Seed Data
-- Seeds at least 20 student records for testing CRUD, Search & Filter
-- Safe script: Uses INSERT IGNORE to prevent duplicate key errors
-- ==========================================================

USE `studenthub`;

INSERT IGNORE INTO `students` (`student_id`, `full_name`, `email`, `mobile`, `department`, `course`, `semester`) VALUES
('25DCS095', 'Om Patel', '25dcs095@charusat.edu.in', '9876543210', 'Information Technology', 'B.Tech', 3),
('25DCS101', 'Aarav Sharma', 'aarav.sharma@charusat.edu.in', '9823456789', 'Computer Science', 'B.Tech', 3),
('25DCS102', 'Priya Patel', 'priya.patel@charusat.edu.in', '9712345678', 'Information Technology', 'B.Tech', 3),
('25BCA045', 'Rohan Mehta', 'rohan.mehta@charusat.edu.in', '9638527410', 'Computer Applications', 'BCA', 2),
('25DCS103', 'Ananya Desai', 'ananya.desai@charusat.edu.in', '9811223344', 'Computer Engineering', 'B.Tech', 3),
('25DCS104', 'Devanshi Shah', 'devanshi.shah@charusat.edu.in', '9822334455', 'Information Technology', 'B.Tech', 3),
('25DCS105', 'Harshil Joshi', 'harshil.joshi@charusat.edu.in', '9833445566', 'Computer Science', 'B.Tech', 3),
('25BCA046', 'Diya Solanki', 'diya.solanki@charusat.edu.in', '9844556677', 'Computer Applications', 'BCA', 2),
('25BCA047', 'Manan Bhatt', 'manan.bhatt@charusat.edu.in', '9855667788', 'Computer Applications', 'BCA', 4),
('25MCA012', 'Krunal Dave', 'krunal.dave@charusat.edu.in', '9866778899', 'Computer Applications', 'MCA', 1),
('25MCA013', 'Riya Trivedi', 'riya.trivedi@charusat.edu.in', '9877889900', 'Computer Applications', 'MCA', 2),
('25MCA014', 'Yash Vora', 'yash.vora@charusat.edu.in', '9888990011', 'Computer Applications', 'MCA', 3),
('25BSC021', 'Tanvi Pandya', 'tanvi.pandya@charusat.edu.in', '9899001122', 'Mathematical Sciences', 'B.Sc', 2),
('25BSC022', 'Meet Parikh', 'meet.parikh@charusat.edu.in', '9711223344', 'Physical Sciences', 'B.Sc', 4),
('25MTC005', 'Neha Kulkarni', 'neha.kulkarni@charusat.edu.in', '9722334455', 'Information Technology', 'M.Tech', 1),
('25MTC006', 'Siddharth Rao', 'siddharth.rao@charusat.edu.in', '9733445566', 'Computer Engineering', 'M.Tech', 2),
('25DCS106', 'Bhavik Panchal', 'bhavik.panchal@charusat.edu.in', '9744556677', 'Information Technology', 'B.Tech', 5),
('25DCS107', 'Ishita Gandhi', 'ishita.gandhi@charusat.edu.in', '9755667788', 'Computer Science', 'B.Tech', 5),
('25BCA048', 'Parth Prajapati', 'parth.prajapati@charusat.edu.in', '9766778899', 'Computer Applications', 'BCA', 1),
('25DCS108', 'Shreya Nair', 'shreya.nair@charusat.edu.in', '9777889900', 'Artificial Intelligence', 'B.Tech', 3),
('25DCS109', 'Vivek Chauhan', 'vivek.chauhan@charusat.edu.in', '9788990011', 'Information Technology', 'B.Tech', 1);
