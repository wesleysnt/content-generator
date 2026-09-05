<?php

namespace App\Enums;

enum RevisionType: string
{
    case AiGeneration = 'ai_generation';
    case AiRegeneration = 'ai_regeneration';
    case SectionRegeneration = 'section_regeneration';
    case TitleRegeneration = 'title_regeneration';
    case WriterEdit = 'writer_edit';
    case Restore = 'restore';
}
