<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Study_transaction
{
	private function unavailable($uncertain = FALSE)
	{
		return array('ok' => FALSE, 'code' => 503, 'failure' => $uncertain ? 'uncertain' : 'unavailable');
	}

	private function rollback($database, $result)
	{
		try
		{
			if ($database->trans_rollback() !== TRUE) { return $this->unavailable(TRUE); }
		}
		catch (Throwable $error)
		{
			log_message('error', 'Study transaction rollback could not be confirmed.');
			return $this->unavailable(TRUE);
		}
		return $result;
	}

	// Own the outermost transaction. Callers must not begin/commit inside the callback.
	public function run($database, callable $operation)
	{
		$debug = $database->db_debug;
		$database->db_debug = FALSE;
		$started = FALSE;
		$committing = FALSE;
		try
		{
			// CI3 nested commits only decrement depth; they do not confirm persistence.
			if ($database->trans_active()) { return $this->unavailable(); }
			if ($database->trans_begin() !== TRUE) { return $this->unavailable(); }
			$started = TRUE;
			$result = $operation();
			if ( ! is_array($result) || ! isset($result['ok']) || ! is_bool($result['ok'])) { throw new UnexpectedValueException('Invalid transaction result.'); }
			if ($database->trans_status() !== TRUE) { return $this->rollback($database, $this->unavailable()); }
			if ( ! $result['ok'])
			{
				if ( ! isset($result['code']) || ! is_int($result['code']) || ! in_array($result['code'], array(400, 403, 404, 409), TRUE)) { $result = $this->unavailable(); }
				return $this->rollback($database, $result);
			}
			$committing = TRUE;
			// A failed acknowledgement cannot prove whether the server committed.
			if ($database->trans_commit() !== TRUE) { return $this->rollback($database, $this->unavailable(TRUE)); }
			return $result;
		}
		catch (Throwable $error)
		{
			// Driver logging is a separate policy; never append exception/SQL here.
			log_message('error', 'Study transaction failed; inspect service availability.');
			$result = $this->unavailable($committing);
			return $started ? $this->rollback($database, $result) : $result;
		}
		finally { $database->db_debug = $debug; }
	}
}
