-- ============================================================
-- Soft delete strategy:
--
-- ADDED deleted_at:
--   employees          → already had it ✓
--   devices            → already had it ✓
--   files              → already had it ✓
--   companies          → config entity, deletion must be recoverable
--   clock_zones        → config entity tied to hardware, recoverable
--   work_calendars     → referenced by employees, must be recoverable
--   time_policies      → versioned config, recoverable
--   employee_day_schedule  → schedule data, recoverable
--   schedule_exceptions    → schedule data, recoverable
--   absence_requests   → HR record, legal traceability
--   correction_requests → audit trail, must be recoverable
--   notifications      → user-facing, recoverable
--   notification_preferences → user config, recoverable
--   push_devices       → device registration, recoverable
--
-- NOT added deleted_at (intentional):
--   manager_employees  → temporal relation (start_date/end_date covers it)
--   holidays           → simple config, recreatable; active flag sufficient
--   time_records       → immutable audit log; corrected flag covers it
--   work_sessions      → derived from time_records; status covers lifecycle
--   incidents          → resolved flag covers lifecycle; audit trail
--   closing_periods    → closed flag covers it; financial record
--   sessions           → revoked_at already covers it
--   login_attempts     → immutable security log, never deleted
--   exports            → status covers lifecycle
--   audit_log          → NEVER deleted, compliance record
--   notification_deliveries → immutable delivery log
-- ============================================================

CREATE TABLE `employees` (
  `id` char(26) PRIMARY KEY,
  `name` varchar(255),
  `email` varchar(255) UNIQUE,
  `password_hash` varchar(255),
  `employee_code` varchar(100) UNIQUE,
  `role` varchar(20) DEFAULT 'employee', -- admin | manager | employee
  `employment_status` smallint,
  `hire_date` date,
  `termination_date` date,
  `company_id` char(26),
  `work_calendar_id` char(26),
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL
    DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL  -- soft delete
);


CREATE TABLE `manager_employees` (
  `id` char(26) PRIMARY KEY,
  `employee_id` char(26),
  `manager_id` char(26),
  `start_date` date,
  `end_date` date,       -- end_date = logical delete for this relation
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP
);


CREATE TABLE `companies` (
  `id` char(26) PRIMARY KEY,
  `name` varchar(255),
  `address` text,
  `province` text,
  `active` tinyint(1),
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL
    DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL  -- soft delete
);


CREATE TABLE `clock_zones` (
  `id` char(26) PRIMARY KEY,
  `company_id` char(26),
  `name` varchar(255),
  `ip` varchar(255),
  `type` varchar(50),
  `active` tinyint(1),
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL  -- soft delete
);


CREATE TABLE `work_calendars` (
  `id` char(26) PRIMARY KEY,
  `name` varchar(255),
  `timezone` varchar(100) NOT NULL DEFAULT 'Europe/Madrid',
  `company_id` char(26),
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL  -- soft delete
);


CREATE TABLE `holidays` (
  `id` char(26) PRIMARY KEY,
  `work_calendar_id` char(26),
  `name` varchar(255),
  `date` date,
  `type` varchar(20),
  `mandatory` boolean,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
  -- no soft delete: simple config row, recreatable; hard delete is fine
);


CREATE TABLE `time_policies` (
  `id` char(26) PRIMARY KEY,
  `name` varchar(255),
  `company_id` char(26),
  `clock_in_tolerance_min` int,
  `clock_out_tolerance_min` int,
  `max_hours_per_day` int,
  `max_hours_per_week` int,
  `allow_overtime` tinyint(1),
  `rounding_minutes` int,
  `active` tinyint(1),
  `version` int,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL
    DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL  -- soft delete
);


CREATE TABLE `employee_day_schedule` (
  `id` char(26) PRIMARY KEY,
  `employee_id` char(26),
  `weekday` smallint,
  `start_time` time,
  `end_time` time,
  `active` tinyint(1),
  `start_date` date,
  `end_date` date,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL  -- soft delete
);


CREATE TABLE `schedule_exceptions` (
  `id` char(26) PRIMARY KEY,
  `employee_id` char(26),
  `date` date,
  `type` varchar(30),
  `start_time` time,
  `end_time` time,
  `reason` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL  -- soft delete
);


CREATE TABLE `devices` (
  `id` char(26) PRIMARY KEY,
  `user_id` char(26),
  `name` varchar(255),
  `platform` varchar(20),
  `fingerprint` varchar(255),
  `last_access` timestamp NULL DEFAULT NULL,
  `trusted` tinyint(1),
  `blocked` tinyint(1),
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL
    DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL  -- soft delete
);


CREATE TABLE `time_records` (
  `id` char(26) PRIMARY KEY,
  `employee_id` char(26),
  `type` smallint,
  `recorded_at` timestamp NULL DEFAULT NULL,
  `clock_method` varchar(30),
  `validation_method` varchar(30),
  `device_id` char(26),
  `clock_zone_id` char(26),
  `ip` varchar(100),
  `user_agent` text,
  `risk_score` decimal(5,2),
  `is_suspicious` tinyint(1),
  `origin` varchar(30),
  `sync_id` varchar(255),
  `synced_at` timestamp NULL DEFAULT NULL,
  `corrected` tinyint(1) DEFAULT false,  -- corrected flag = logical invalidation
  `original_record_id` char(26),
  `note` text,
  `record_hash` varchar(255),
  `previous_hash` varchar(255),
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
  -- no soft delete: immutable audit log; use corrected=true to invalidate
);


CREATE TABLE `work_sessions` (
  `id` char(26) PRIMARY KEY,
  `employee_id` char(26),
  `clock_in_record_id` char(26),
  `clock_out_record_id` char(26),
  `clocked_in_at` timestamp NULL DEFAULT NULL,
  `clocked_out_at` timestamp NULL DEFAULT NULL,
  `status` varchar(30),  -- status covers full lifecycle
  `processed` tinyint(1),
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
  -- no soft delete: status field covers lifecycle (open/closed/cancelled)
);


CREATE TABLE `incidents` (
  `id` char(26) PRIMARY KEY,
  `employee_id` char(26),
  `work_session_id` char(26),
  `type` varchar(50),
  `severity` varchar(20),
  `description` text,
  `resolved` tinyint(1),  -- resolved flag covers lifecycle
  `resolved_by` char(26),
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
  -- no soft delete: resolved flag covers it; HR audit trail
);


CREATE TABLE `absence_requests` (
  `id` char(26) PRIMARY KEY,
  `employee_id` char(26),
  `type` varchar(30),
  `start_date` date,
  `end_date` date,
  `request_reason` text,
  `status` varchar(20),
  `reviewed_by` char(26),
  `review_comment` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL
    DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,
  `document_id` char(26),
  `deleted_at` timestamp NULL DEFAULT NULL  -- soft delete: legal/HR traceability
);


CREATE TABLE `correction_requests` (
  `id` char(26) PRIMARY KEY,
  `time_record_id` char(26),
  `requested_by` char(26),
  `new_datetime` timestamp NULL DEFAULT NULL,
  `reason` text,
  `status` varchar(20),
  `reviewed_by` char(26),
  `applied_at` timestamp NULL DEFAULT NULL,
  `corrected_record_id` char(26),
  `review_note` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL
    DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL  -- soft delete: audit trail
);


CREATE TABLE `employee_applications` (
  `id` char(26) PRIMARY KEY,
  `candidate_name` varchar(255),
  `candidate_surname` varchar(255),
  `address` varchar(255),
  `phone` varchar(30),
  `email` varchar(255),
  `document_type` varchar(30),
  `document_number` varchar(50),
  `social_security_number` varchar(30) NULL DEFAULT NULL,
  `notes` text NULL DEFAULT NULL,
  `status` varchar(20) DEFAULT 'pending',
  `reviewed_by` char(26) NULL DEFAULT NULL,
  `review_comment` text NULL DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL
    DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL
);

CREATE INDEX `employee_applications_index_0` ON `employee_applications` (`status`, `created_at`);
CREATE INDEX `employee_applications_index_1` ON `employee_applications` (`email`);


CREATE TABLE `closing_periods` (
  `id` char(26) PRIMARY KEY,
  `name` varchar(100),
  `start_date` date,
  `end_date` date,
  `closed` tinyint(1) DEFAULT false,  -- closed flag covers lifecycle
  `closed_by` char(26),
  `closed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
  -- no soft delete: closed flag covers it; financial/payroll record
);


CREATE TABLE `sessions` (
  `id` char(26) PRIMARY KEY,
  `user_id` char(26),
  `token_hash` varchar(255),
  `ip` varchar(100),
  `user_agent` text,
  `expires_at` timestamp NULL DEFAULT NULL,
  `last_access` timestamp NULL DEFAULT NULL,
  `revoked_at` timestamp NULL DEFAULT NULL,  -- revoked_at = logical delete
  `revoked_by` char(26),
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
  -- no soft delete: revoked_at already serves this purpose
);


CREATE TABLE `login_attempts` (
  `id` char(26) PRIMARY KEY,
  `identifier` varchar(255),
  `ip` varchar(100),
  `successful` tinyint(1),
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
  -- no soft delete: immutable security log
);


CREATE TABLE `notifications` (
  `id` char(26) PRIMARY KEY,
  `user_id` char(26),
  `title` varchar(255),
  `message` text,
  `event_type` varchar(50),
  `target_url` varchar(500),
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL  -- soft delete: user can "dismiss/delete"
);


CREATE TABLE `notification_preferences` (
  `id` char(26) PRIMARY KEY,
  `user_id` char(26),
  `channel` varchar(30),
  `event_type` varchar(50),
  `enabled` tinyint(1),
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,  -- soft delete: preference history
  UNIQUE KEY uq_user_channel_event (user_id, channel, event_type)
);


CREATE TABLE `push_devices` (
  `id` char(26) PRIMARY KEY,
  `device_id` char(26),
  `push_token` varchar(512) UNIQUE,
  `platform` varchar(50),
  `active` tinyint(1),
  `last_used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL  -- soft delete: token revocation history
);


CREATE TABLE `notification_deliveries` (
  `id` char(26) PRIMARY KEY,
  `notification_id` char(26),
  `channel` varchar(30),
  `status` varchar(20),
  `sent_at` timestamp NULL DEFAULT NULL,
  `error` text
  -- no soft delete: immutable delivery log
);


CREATE TABLE `files` (
  `id` char(26) PRIMARY KEY,
  `uploaded_by` char(26),
  `entity_type` varchar(50),
  `entity_id` char(26),
  `file_name` varchar(255),
  `storage_provider` varchar(50),
  `bucket` varchar(100),
  `storage_key` varchar(500),
  `mime_type` varchar(100),
  `extension` varchar(20),
  `size_bytes` bigint,
  `hash_sha256` varchar(64),
  `visibility` varchar(20),
  `status` varchar(20),
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL  -- soft delete
);


CREATE TABLE `exports` (
  `id` char(26) PRIMARY KEY,
  `requested_by` char(26),
  `type` varchar(50),
  `format` varchar(20),
  `parameters` json,
  `status` varchar(20),  -- status covers lifecycle
  `file_id` char(26),
  `error` text,
  `finished_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
  -- no soft delete: status covers it (pending/done/failed)
);


CREATE TABLE `audit_log` (
  `id` char(26) PRIMARY KEY,
  `actor_id` char(26),
  `action` varchar(100),
  `entity_type` varchar(100),
  `entity_id` char(26),
  `changes` json,
  `reason` text,
  `request_id` varchar(255),
  `origin` varchar(30),
  `ip` varchar(100),
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
  -- NO soft delete: compliance record, must never be deleted
);


-- ============================================================
-- Indexes
-- ============================================================

CREATE INDEX `manager_employees_index_0` ON `manager_employees` (`employee_id`, `start_date`);
CREATE INDEX `manager_employees_index_1` ON `manager_employees` (`manager_id`);

CREATE INDEX `time_records_index_2` ON `time_records` (`employee_id`, `recorded_at`);
CREATE INDEX `time_records_index_3` ON `time_records` (`clock_zone_id`);

CREATE INDEX `work_sessions_index_4` ON `work_sessions` (`employee_id`, `clocked_in_at`);
CREATE INDEX `work_sessions_index_5` ON `work_sessions` (`status`);

CREATE INDEX `incidents_index_6` ON `incidents` (`resolved`);
CREATE INDEX `incidents_index_7` ON `incidents` (`type`);

CREATE INDEX `absence_requests_index_8` ON `absence_requests` (`employee_id`, `status`);
CREATE INDEX `absence_requests_index_9` ON `absence_requests` (`status`, `start_date`);
CREATE INDEX `absence_requests_index_10` ON `absence_requests` (`start_date`, `end_date`);

CREATE INDEX `correction_requests_index_11` ON `correction_requests` (`status`);
CREATE INDEX `correction_requests_index_12` ON `correction_requests` (`time_record_id`);

CREATE INDEX `sessions_index_13` ON `sessions` (`user_id`, `expires_at`);

CREATE INDEX `login_attempts_index_14` ON `login_attempts` (`identifier`);
CREATE INDEX `login_attempts_index_15` ON `login_attempts` (`ip`);
CREATE INDEX `login_attempts_index_16` ON `login_attempts` (`created_at`);

CREATE INDEX `notifications_index_17` ON `notifications` (`user_id`, `read_at`);

CREATE UNIQUE INDEX `notification_preferences_index_18` ON `notification_preferences` (`user_id`, `channel`, `event_type`);
CREATE UNIQUE INDEX `push_devices_index_19` ON `push_devices` (`push_token`);

CREATE INDEX `files_index_20` ON `files` (`entity_type`, `entity_id`);
CREATE INDEX `files_index_21` ON `files` (`uploaded_by`);

CREATE INDEX `exports_index_22` ON `exports` (`requested_by`);
CREATE INDEX `exports_index_23` ON `exports` (`status`);

CREATE INDEX `audit_log_index_24` ON `audit_log` (`actor_id`);
CREATE INDEX `audit_log_index_25` ON `audit_log` (`entity_type`, `entity_id`);
CREATE INDEX `audit_log_index_26` ON `audit_log` (`created_at`);

-- Indexes on deleted_at for fast "WHERE deleted_at IS NULL" queries
CREATE INDEX `employees_index_deleted` ON `employees` (`deleted_at`);
CREATE INDEX `companies_index_deleted` ON `companies` (`deleted_at`);
CREATE INDEX `clock_zones_index_deleted` ON `clock_zones` (`deleted_at`);
CREATE INDEX `work_calendars_index_deleted` ON `work_calendars` (`deleted_at`);
CREATE INDEX `time_policies_index_deleted` ON `time_policies` (`deleted_at`);
CREATE INDEX `employee_day_schedule_index_deleted` ON `employee_day_schedule` (`deleted_at`);
CREATE INDEX `schedule_exceptions_index_deleted` ON `schedule_exceptions` (`deleted_at`);
CREATE INDEX `devices_index_deleted` ON `devices` (`deleted_at`);
CREATE INDEX `absence_requests_index_deleted` ON `absence_requests` (`deleted_at`);
CREATE INDEX `correction_requests_index_deleted` ON `correction_requests` (`deleted_at`);
CREATE INDEX `notifications_index_deleted` ON `notifications` (`deleted_at`);
CREATE INDEX `files_index_deleted` ON `files` (`deleted_at`);


-- ============================================================
-- Foreign Keys
-- ============================================================

ALTER TABLE `employees` ADD FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`);
ALTER TABLE `employees` ADD FOREIGN KEY (`work_calendar_id`) REFERENCES `work_calendars` (`id`);

ALTER TABLE `manager_employees` ADD FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`);
ALTER TABLE `manager_employees` ADD FOREIGN KEY (`manager_id`) REFERENCES `employees` (`id`);

ALTER TABLE `clock_zones` ADD FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`);

ALTER TABLE `time_policies` ADD FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`);

ALTER TABLE `employee_day_schedule` ADD FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`);

ALTER TABLE `devices` ADD FOREIGN KEY (`user_id`) REFERENCES `employees` (`id`);

ALTER TABLE `time_records` ADD FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`);
ALTER TABLE `time_records` ADD FOREIGN KEY (`device_id`) REFERENCES `devices` (`id`);
ALTER TABLE `time_records` ADD FOREIGN KEY (`clock_zone_id`) REFERENCES `clock_zones` (`id`);
ALTER TABLE `time_records` ADD FOREIGN KEY (`original_record_id`) REFERENCES `time_records` (`id`);

ALTER TABLE `work_sessions` ADD FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`);
ALTER TABLE `work_sessions` ADD FOREIGN KEY (`clock_in_record_id`) REFERENCES `time_records` (`id`);
ALTER TABLE `work_sessions` ADD FOREIGN KEY (`clock_out_record_id`) REFERENCES `time_records` (`id`);

ALTER TABLE `incidents` ADD FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`);
ALTER TABLE `incidents` ADD FOREIGN KEY (`work_session_id`) REFERENCES `work_sessions` (`id`);
ALTER TABLE `incidents` ADD FOREIGN KEY (`resolved_by`) REFERENCES `employees` (`id`);

ALTER TABLE `absence_requests` ADD FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`);
ALTER TABLE `absence_requests` ADD FOREIGN KEY (`reviewed_by`) REFERENCES `employees` (`id`);

ALTER TABLE `correction_requests` ADD FOREIGN KEY (`time_record_id`) REFERENCES `time_records` (`id`);
ALTER TABLE `correction_requests` ADD FOREIGN KEY (`requested_by`) REFERENCES `employees` (`id`);
ALTER TABLE `correction_requests` ADD FOREIGN KEY (`reviewed_by`) REFERENCES `employees` (`id`);

ALTER TABLE `closing_periods` ADD FOREIGN KEY (`closed_by`) REFERENCES `employees` (`id`);

ALTER TABLE `holidays` ADD FOREIGN KEY (`work_calendar_id`) REFERENCES `work_calendars` (`id`);

ALTER TABLE `sessions` ADD FOREIGN KEY (`user_id`) REFERENCES `employees` (`id`);
ALTER TABLE `sessions` ADD FOREIGN KEY (`revoked_by`) REFERENCES `employees` (`id`);

ALTER TABLE `notifications` ADD FOREIGN KEY (`user_id`) REFERENCES `employees` (`id`);

ALTER TABLE `notification_preferences` ADD FOREIGN KEY (`user_id`) REFERENCES `employees` (`id`);

ALTER TABLE `files` ADD FOREIGN KEY (`uploaded_by`) REFERENCES `employees` (`id`);

ALTER TABLE `exports` ADD FOREIGN KEY (`requested_by`) REFERENCES `employees` (`id`);
ALTER TABLE `exports` ADD FOREIGN KEY (`file_id`) REFERENCES `files` (`id`);

ALTER TABLE `audit_log` ADD FOREIGN KEY (`actor_id`) REFERENCES `employees` (`id`);

ALTER TABLE `absence_requests` ADD FOREIGN KEY (`document_id`) REFERENCES `files` (`id`);

ALTER TABLE `correction_requests` ADD FOREIGN KEY (`corrected_record_id`) REFERENCES `time_records` (`id`);

ALTER TABLE `work_calendars` ADD FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`);

ALTER TABLE `schedule_exceptions` ADD FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`);

ALTER TABLE `push_devices` ADD FOREIGN KEY (`device_id`) REFERENCES `devices` (`id`);

ALTER TABLE `notification_deliveries` ADD FOREIGN KEY (`notification_id`) REFERENCES `notifications` (`id`);
