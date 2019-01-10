<?php

class OfdReceiptItem
{
	public $label;
	public $amount;
	public $price;
	public $quantity;
	public $vat;
	public $type = 'product';

	public function toArray()
	{
		return array(
			'pg_label' => $this->label,
			#'pg_amount' => $this->amount,
			'pg_price' => $this->price,
			'pg_quantity' => $this->quantity,
			'pg_vat' => $this->vat,
			'pg_type' => $this->type,
			
		);
	}
}
