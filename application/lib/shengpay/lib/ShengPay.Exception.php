<?php
/**
 * 
 * 支付API异常类
 *
 */
class ShengPayException extends Exception {
	public function errorMessage()
	{
		return $this->getMessage();
	}
}
