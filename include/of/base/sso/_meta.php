<?php
return of::config('_of.sso.url') ? array() : array(
    'name' => 'Single Sign On',
    'gets' => array(
        'c' => 'of_base_sso_main'
    )
);