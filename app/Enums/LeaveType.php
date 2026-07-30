<?php
namespace App\Enums;
enum LeaveType: string
{
    case annual = '特休假';
    case sick = '病假';
    case personal = '事假';
    case official = '公假';
    case menstrual = '生理假';
    case compensatory = '補休';
}