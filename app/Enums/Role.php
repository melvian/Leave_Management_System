<?php
namespace App\Enums;
enum Role: string
{
    case employee = '員工';
    case manager  = '部門主管';
    case hr       = '人資部';
    case admin    = '系統管理者';
}