-- Thesis Finder database (3NF)
-- MySQL 5.7+ / MariaDB

DROP DATABASE IF EXISTS thesis_finder;
CREATE DATABASE thesis_finder CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE thesis_finder;

SET FOREIGN_KEY_CHECKS = 0;

-- user (super entity for Student / Teacher ISA)
CREATE TABLE user (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    email           VARCHAR(120) NOT NULL UNIQUE,
    password        VARCHAR(255) NOT NULL,
    name            VARCHAR(120) NOT NULL,
    student_flag    TINYINT(1) NOT NULL DEFAULT 0,
    teacher_flag    TINYINT(1) NOT NULL DEFAULT 0,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- student ISA user
CREATE TABLE student (
    student_id                  VARCHAR(20) PRIMARY KEY,
    user_id                     INT NOT NULL UNIQUE,
    cgpa                        DECIMAL(3,2),
    preferable_coding_language  VARCHAR(80),
    thesis_starting_time        VARCHAR(40),
    project_starting_time       VARCHAR(40),
    dept                        VARCHAR(80),
    semester                    VARCHAR(40),
    undergrad_flag              TINYINT(1) NOT NULL DEFAULT 1,
    postgrad_flag               TINYINT(1) NOT NULL DEFAULT 0,
    CONSTRAINT fk_student_user  FOREIGN KEY (user_id) REFERENCES user(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- teacher ISA user
CREATE TABLE teacher (
    user_id            INT PRIMARY KEY,
    consultation_time  VARCHAR(120),
    CONSTRAINT fk_teacher_user FOREIGN KEY (user_id) REFERENCES user(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- teacher multivalued attributes
CREATE TABLE teacher_project_interest (
    id               INT NOT NULL,
    project_interest VARCHAR(120) NOT NULL,
    PRIMARY KEY (id, project_interest),
    CONSTRAINT fk_tpi_teacher FOREIGN KEY (id) REFERENCES teacher(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE teacher_thesis_interest (
    id              INT NOT NULL,
    thesis_interest VARCHAR(120) NOT NULL,
    PRIMARY KEY (id, thesis_interest),
    CONSTRAINT fk_tti_teacher FOREIGN KEY (id) REFERENCES teacher(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE teacher_thesis_slot (
    id          INT NOT NULL,
    thesis_slot VARCHAR(120) NOT NULL,
    PRIMARY KEY (id, thesis_slot),
    CONSTRAINT fk_tts_teacher FOREIGN KEY (id) REFERENCES teacher(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- user multivalued attributes
CREATE TABLE user_previous_work (
    user_id       INT NOT NULL,
    previous_work VARCHAR(255) NOT NULL,
    PRIMARY KEY (user_id, previous_work),
    CONSTRAINT fk_upw_user FOREIGN KEY (user_id) REFERENCES user(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE user_project_interest (
    user_id          INT NOT NULL,
    project_interest VARCHAR(120) NOT NULL,
    PRIMARY KEY (user_id, project_interest),
    CONSTRAINT fk_upi_user FOREIGN KEY (user_id) REFERENCES user(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE user_project_skill (
    user_id INT NOT NULL,
    skill   VARCHAR(120) NOT NULL,
    PRIMARY KEY (user_id, skill),
    CONSTRAINT fk_ups_user FOREIGN KEY (user_id) REFERENCES user(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE user_thesis_interest (
    user_id         INT NOT NULL,
    thesis_interest VARCHAR(120) NOT NULL,
    PRIMARY KEY (user_id, thesis_interest),
    CONSTRAINT fk_uti_user FOREIGN KEY (user_id) REFERENCES user(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- work (thesis / project)
CREATE TABLE work (
    project_id    INT AUTO_INCREMENT PRIMARY KEY,
    title         VARCHAR(200) NOT NULL,
    description   TEXT,
    owner_id      INT NOT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    supervisor_id INT DEFAULT NULL,
    thesis_flag   TINYINT(1) NOT NULL DEFAULT 0,
    project_flag  TINYINT(1) NOT NULL DEFAULT 0,
    CONSTRAINT fk_work_owner      FOREIGN KEY (owner_id)      REFERENCES user(id)         ON DELETE CASCADE,
    CONSTRAINT fk_work_supervisor FOREIGN KEY (supervisor_id) REFERENCES teacher(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- student joined project
CREATE TABLE student_join_project (
    project_id INT NOT NULL,
    id         INT NOT NULL,
    joined_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (project_id, id),
    CONSTRAINT fk_sjp_work    FOREIGN KEY (project_id) REFERENCES work(project_id) ON DELETE CASCADE,
    CONSTRAINT fk_sjp_student FOREIGN KEY (id)         REFERENCES student(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- project status (multivalued)
CREATE TABLE project_status (
    project_id      INT NOT NULL,
    project_status  VARCHAR(40) NOT NULL,
    PRIMARY KEY (project_id, project_status),
    CONSTRAINT fk_ps_work FOREIGN KEY (project_id) REFERENCES work(project_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- team request
CREATE TABLE team_request (
    request_id    INT AUTO_INCREMENT PRIMARY KEY,
    project_id    INT NOT NULL,
    requester_id  INT NOT NULL,
    requested_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tr_work      FOREIGN KEY (project_id)   REFERENCES work(project_id) ON DELETE CASCADE,
    CONSTRAINT fk_tr_requester FOREIGN KEY (requester_id) REFERENCES user(id)         ON DELETE CASCADE
) ENGINE=InnoDB;

-- team request status (multivalued)
CREATE TABLE team_request_status (
    request_id          INT NOT NULL,
    team_request_status VARCHAR(40) NOT NULL,
    PRIMARY KEY (request_id, team_request_status),
    CONSTRAINT fk_trs_req FOREIGN KEY (request_id) REFERENCES team_request(request_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- message
CREATE TABLE message (
    message_id  INT AUTO_INCREMENT PRIMARY KEY,
    sender_id   INT NOT NULL,
    receiver_id INT NOT NULL,
    body        TEXT NOT NULL,
    sent_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_msg_sender   FOREIGN KEY (sender_id)   REFERENCES user(id) ON DELETE CASCADE,
    CONSTRAINT fk_msg_receiver FOREIGN KEY (receiver_id) REFERENCES user(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- sends / recieves (link message to user and optionally a request)
CREATE TABLE sends_recieves (
    message_id INT NOT NULL,
    user_id    INT NOT NULL,
    request_id INT DEFAULT NULL,
    PRIMARY KEY (message_id, user_id),
    CONSTRAINT fk_sr_msg  FOREIGN KEY (message_id) REFERENCES message(message_id)      ON DELETE CASCADE,
    CONSTRAINT fk_sr_user FOREIGN KEY (user_id)    REFERENCES user(id)                 ON DELETE CASCADE,
    CONSTRAINT fk_sr_req  FOREIGN KEY (request_id) REFERENCES team_request(request_id) ON DELETE SET NULL
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

CREATE INDEX idx_work_owner       ON work(owner_id);
CREATE INDEX idx_work_supervisor  ON work(supervisor_id);
CREATE INDEX idx_work_flags       ON work(thesis_flag, project_flag);
CREATE INDEX idx_message_sender   ON message(sender_id);
CREATE INDEX idx_message_receiver ON message(receiver_id);
CREATE INDEX idx_tr_project       ON team_request(project_id);
CREATE INDEX idx_tr_requester     ON team_request(requester_id);
