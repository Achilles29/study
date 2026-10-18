<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Add_commerce_catalog extends CI_Migration
{
	public function up()
	{
		$queries = array(
			"ALTER TABLE st_packages ADD revision INT UNSIGNED NOT NULL DEFAULT 1,ADD is_official TINYINT UNSIGNED NOT NULL DEFAULT 0",
			"ALTER TABLE st_wallet_entries MODIFY entry_type ENUM('top_up','package_purchase','refund','adjustment','voucher_purchase') NOT NULL",
			"CREATE TABLE st_commerce_offers (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
				kind ENUM('package','voucher','topup','platinum','slot') NOT NULL,
				title VARCHAR(180) NOT NULL,package_id BIGINT UNSIGNED NULL,
				price BIGINT UNSIGNED NOT NULL,credit_amount INT UNSIGNED NOT NULL DEFAULT 0,
				access_days INT UNSIGNED NOT NULL DEFAULT 0,voucher_days INT UNSIGNED NOT NULL DEFAULT 0,
				status ENUM('draft','active','paused') NOT NULL DEFAULT 'draft',revision INT UNSIGNED NOT NULL DEFAULT 1,
				created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL,
				KEY idx_offer_status(status,kind),CONSTRAINT fk_offer_package FOREIGN KEY(package_id) REFERENCES st_packages(id) ON DELETE RESTRICT
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
			"CREATE TABLE st_commerce_purchases (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,owner_user_id BIGINT UNSIGNED NOT NULL,
				offer_id BIGINT UNSIGNED NOT NULL,request_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
				request_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,snapshot_json LONGTEXT NOT NULL,
				status ENUM('available','redeemed','fulfilled','refunded') NOT NULL,
				learner_user_id BIGINT UNSIGNED NULL,redeem_by DATETIME NULL,created_at DATETIME NOT NULL,redeemed_at DATETIME NULL,
				UNIQUE KEY uq_purchase_request(owner_user_id,request_key),KEY idx_purchase_owner(owner_user_id,id),
				CONSTRAINT fk_purchase_owner FOREIGN KEY(owner_user_id) REFERENCES st_users(id) ON DELETE RESTRICT,
				CONSTRAINT fk_purchase_learner FOREIGN KEY(learner_user_id) REFERENCES st_users(id) ON DELETE RESTRICT,
				CONSTRAINT fk_purchase_offer FOREIGN KEY(offer_id) REFERENCES st_commerce_offers(id) ON DELETE RESTRICT
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
			"CREATE TABLE st_commerce_grants (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,purchase_id BIGINT UNSIGNED NOT NULL,
				owner_user_id BIGINT UNSIGNED NOT NULL,learner_user_id BIGINT UNSIGNED NOT NULL,
				package_id BIGINT UNSIGNED NULL,kind ENUM('package','platinum') NOT NULL,course_ids_json LONGTEXT NOT NULL,
				family_practice_enabled TINYINT UNSIGNED NOT NULL DEFAULT 0,
				starts_at DATETIME NOT NULL,ends_at DATETIME NOT NULL,status ENUM('active','revoked') NOT NULL DEFAULT 'active',
				UNIQUE KEY uq_commerce_grant_purchase(purchase_id),KEY idx_grant_learner(learner_user_id,status,ends_at),KEY idx_grant_owner(owner_user_id,kind,status,ends_at),
				CONSTRAINT fk_grant_purchase FOREIGN KEY(purchase_id) REFERENCES st_commerce_purchases(id) ON DELETE RESTRICT,
				CONSTRAINT fk_grant_owner FOREIGN KEY(owner_user_id) REFERENCES st_users(id) ON DELETE RESTRICT,
				CONSTRAINT fk_grant_learner FOREIGN KEY(learner_user_id) REFERENCES st_users(id) ON DELETE RESTRICT,
				CONSTRAINT fk_grant_package FOREIGN KEY(package_id) REFERENCES st_packages(id) ON DELETE RESTRICT
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
			"CREATE TABLE st_family_slots (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,owner_user_id BIGINT UNSIGNED NOT NULL,
				purchase_id BIGINT UNSIGNED NOT NULL,created_at DATETIME NOT NULL,
				UNIQUE KEY uq_family_slot_purchase(purchase_id),KEY idx_family_slot_owner(owner_user_id),
				CONSTRAINT fk_slot_owner FOREIGN KEY(owner_user_id) REFERENCES st_users(id) ON DELETE RESTRICT,
				CONSTRAINT fk_slot_purchase FOREIGN KEY(purchase_id) REFERENCES st_commerce_purchases(id) ON DELETE RESTRICT
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
		);
		foreach ($queries as $sql) { if ($this->db->query($sql) === FALSE) { throw new RuntimeException('Commerce schema unavailable.'); } }
	}
	public function down()
	{
		throw new RuntimeException('Commerce history must not be deleted automatically.'); }
}
