<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Migration_Add_sandbox_checkout extends CI_Migration
{
	public function up()
	{
		$queries = array(
			"ALTER TABLE st_wallets ADD environment ENUM('legacy','sandbox') NOT NULL DEFAULT 'legacy',DROP INDEX uq_st_wallet_owner,ADD UNIQUE KEY uq_wallet_owner_environment(owner_user_id,environment)",
			"ALTER TABLE st_payment_orders ADD environment ENUM('legacy','sandbox') NOT NULL DEFAULT 'legacy',ADD offer_id BIGINT UNSIGNED NULL,ADD config_id BIGINT UNSIGNED NULL,ADD request_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,ADD request_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,ADD snapshot_json LONGTEXT NULL,ADD provider_transaction_id VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NULL,ADD qr_url VARCHAR(500) NULL,ADD checked_at DATETIME NULL,ADD review_required TINYINT UNSIGNED NOT NULL DEFAULT 0,ADD UNIQUE KEY uq_payment_request(user_id,request_key),ADD UNIQUE KEY uq_payment_transaction(environment,provider_transaction_id),ADD CONSTRAINT fk_order_offer FOREIGN KEY(offer_id) REFERENCES st_commerce_offers(id) ON DELETE RESTRICT,ADD CONSTRAINT fk_order_config FOREIGN KEY(config_id) REFERENCES st_payment_configs(id) ON DELETE RESTRICT",
			"ALTER TABLE st_commerce_purchases ADD environment ENUM('sandbox') NOT NULL DEFAULT 'sandbox',ADD payment_order_id BIGINT UNSIGNED NULL,ADD UNIQUE KEY uq_purchase_order(payment_order_id),ADD CONSTRAINT fk_commerce_order FOREIGN KEY(payment_order_id) REFERENCES st_payment_orders(id) ON DELETE RESTRICT",
			"ALTER TABLE st_commerce_grants ADD environment ENUM('sandbox') NOT NULL DEFAULT 'sandbox'",
			"ALTER TABLE st_family_slots ADD environment ENUM('sandbox') NOT NULL DEFAULT 'sandbox'"
		);
		foreach ($queries as $sql) { if ($this->db->query($sql) === FALSE) { throw new RuntimeException('Sandbox schema unavailable.'); } }
	}
	public function down()
	{
		throw new RuntimeException('Payment evidence cannot be removed automatically.'); }
}
