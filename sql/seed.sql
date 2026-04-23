-- Sample data. Run after schema.sql.
-- All demo accounts use password: password123

USE thesis_finder;

SET @pw := '$2y$12$6FUQhRWTZQwtk/cwZCRwiuUulCIX21it3R6GFycQOgucfkkXri.q6';

INSERT INTO user (email, password, name, student_flag, teacher_flag) VALUES
 ('alice@g.bracu.ac.bd',    @pw, 'Alice Rahman', 1, 0),
 ('bob@g.bracu.ac.bd',      @pw, 'Bob Khan',     1, 0),
 ('carol@g.bracu.ac.bd',    @pw, 'Carol Ahmed',  1, 0),
 ('drsmith@bracu.ac.bd',    @pw, 'Dr. Smith',    0, 1),
 ('drjane@bracu.ac.bd',     @pw, 'Dr. Jane Doe', 0, 1);

INSERT INTO student (user_id, cgpa, preferable_coding_language, thesis_starting_time, project_starting_time, dept, semester, undergrad_flag, postgrad_flag) VALUES
 (1, 3.85, 'Python',     'Spring 2026', 'Fall 2025', 'CSE', '8th', 1, 0),
 (2, 3.60, 'Java',       'Fall 2025',   'Spring 2026', 'CSE', '7th', 1, 0),
 (3, 3.90, 'C++',        'Spring 2026', 'Summer 2025','EEE', '6th', 1, 0);

INSERT INTO teacher (user_id, consultation_time) VALUES
 (4, 'Sun/Tue 3-5 PM'),
 (5, 'Mon/Wed 10-12 AM');

INSERT INTO teacher_project_interest (id, project_interest) VALUES
 (4, 'Machine Learning'), (4, 'NLP'),
 (5, 'Computer Vision'),  (5, 'IoT');

INSERT INTO teacher_thesis_interest (id, thesis_interest) VALUES
 (4, 'Deep Learning'),    (4, 'Ethical AI'),
 (5, 'Robotics'),         (5, 'Edge Computing');

INSERT INTO teacher_thesisslot (id, thesis_slot) VALUES
 (4, 'Spring 2026'), (4, 'Fall 2026'),
 (5, 'Summer 2025'), (5, 'Spring 2026');

INSERT INTO user_project_iskill (user_id, skill) VALUES
 (1, 'Python'), (1, 'TensorFlow'),
 (2, 'Java'),   (2, 'Spring Boot'),
 (3, 'C++'),    (3, 'OpenCV');

INSERT INTO user_project_interest (user_id, project_interest) VALUES
 (1, 'AI Chatbots'), (2, 'Web Apps'), (3, 'Image Processing');

INSERT INTO user_thesis_interest (user_id, thesis_interest) VALUES
 (1, 'NLP'), (2, 'Cloud Computing'), (3, 'Autonomous Systems');

INSERT INTO user_previous_work (user_id, previous_work) VALUES
 (1, 'Sentiment Analyzer (CSE438)'),
 (2, 'Online Bookstore (CSE370)'),
 (3, 'Face Detection App');

INSERT INTO work (title, description, owner_id, supervisor_id, thesis_flag, project_flag) VALUES
 ('Bangla Chatbot for Student Advising',
  'Build a low-resource Bangla chatbot that answers BRACU undergrad advising questions.',
  1, 4, 0, 1),
 ('Real-time Pothole Detection',
  'Vision-based thesis detecting potholes from dashcam feeds for Dhaka roads.',
  3, 5, 1, 0),
 ('Marketplace Web App for Students',
  'Full-stack project for peer-to-peer item sales inside campus.',
  2, NULL, 0, 1);

INSERT INTO project_status (project_id, project_status) VALUES
 (1, 'open'), (2, 'in_progress'), (3, 'open');

INSERT INTO student_join_project (project_id, id) VALUES
 (1, 1), (1, 2), (2, 3);

INSERT INTO team_request (project_id, requester_id) VALUES
 (3, 1), (1, 3);

INSERT INTO team_request_status (request_id, team_request_status) VALUES
 (1, 'pending'), (2, 'pending');

INSERT INTO message (sender_id, receiver_id, body) VALUES
 (1, 2, 'Hey Bob, want to team up for the marketplace project?'),
 (2, 1, 'Sure Alice, let me check the scope and get back.');

INSERT INTO sends_recieves (message_id, user_id, request_id) VALUES
 (1, 1, NULL),
 (2, 2, NULL);
