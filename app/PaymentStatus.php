<?php

namespace App;

enum PaymentStatus: string
{
    case SUCCESS = 'success';
    case FAILED = 'failed';
}
