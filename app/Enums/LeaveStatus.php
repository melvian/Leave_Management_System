<?php
namespace App\Enums;
enum LeaveStatus: string
{
    case draft = '草稿';
    case pending = '簽核中';
    case approved = '已核准';
    case rejected = '已拒絕';
}