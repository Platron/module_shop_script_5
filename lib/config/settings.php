<?php

return array(
    'merchant'   => array(
        'value'        => '',
        'title'        => 'Номер магазина (merchant_id)',
        'description'  => 'Можно посмотреть <a target="_blank" href="https://www.platron.ru/admin/merchants.php">в личном кабинете platron</a>',
        'control_type' => waHtmlControl::INPUT,
    ),

    'secret_key'   => array(
        'value'        => '',
        'title'        => 'Секретный ключ магазина',
        'description'  => 'Можно посмотреть <a target="_blank" href="https://www.platron.ru/admin/merchants.php">в личном кабинете platron</a>',
        'control_type' => waHtmlControl::INPUT,
    ),
	'lifetime'         => array(
        'value'        => '0',
        'title'        => 'Время жизни счета в минутах',
        'description'  => 'Максимальное значение 60*24*7 (время, пока можно оплатить заказ). Запрос check невозможно реализовать из-за отсутствия доступа из плагина к заказу. Поэтому заказы нельзя удалять или отменять.',
        'control_type' => waHtmlControl::INPUT,
    ),
    'testmode'         => array(
        'value'        => '1',
        'title'        => 'Тестовый режим',
        'description'  => 'Для того, чтобы выйти из тестового режима, нужно снять галочку',
        'control_type' => waHtmlControl::CHECKBOX,
    ),
    'ofd_send_receipt' => array(
        'value'        => '0',
        'title'        => 'Создать чек в ОФД',
        'description'  => 'Отправить данные заказа для формирования чека в ОФД',
        'control_type' => waHtmlControl::CHECKBOX,
    ),
    'VAT_type'         => array(
        'value'        => '0',
        'title'        => 'Ставка НДС',
        'description'  => 'Указывается в чеке отправляемом в ОФД',
        'control_type' => waHtmlControl::SELECT,
        'options'      => array(
			     'none' => 'Не облагается',
                             '0' => '0%',
                             '10' => '10%',
                             '18' => '18%',
                             '110' => '10/110',
                             '118' => '18/118'
                          )
    )

);
