<?php

namespace mpfeli\mercadopago;

class ext extends \phpbb\extension\base
{
    public function is_enableable()
	{
		return phpbb_version_compare(PHPBB_VERSION, '3.3.2', '>=');
	}
}
