<?php

namespace App\Enums;

enum InboundSource: string
{
    case Email = 'email';
    case Mcp = 'mcp';
    case Upload = 'upload';
}
