<?php
namespace App\Enums;
enum OvertimeStatus: string
{
    case pending  = '待確認';
    case approved = '已確認';
    case rejected = '已駁回';
}