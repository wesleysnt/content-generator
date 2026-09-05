<?php

namespace App\Enums;

enum AngleType: string
{
    case Educational = 'educational';
    case ProblemSolution = 'problem_solution';
    case PracticalGuide = 'practical_guide';
    case DataDriven = 'data_driven';
    case StoryCaseStudy = 'story_case_study';
    case ExpertAnalysis = 'expert_analysis';
    case CommonMistakes = 'common_mistakes';
    case Listicle = 'listicle';
    case Comparison = 'comparison';
    case FaqDriven = 'faq_driven';

    public function label(): string
    {
        return match ($this) {
            self::Educational => 'Educational',
            self::ProblemSolution => 'Problem/Solution',
            self::PracticalGuide => 'Practical Guide',
            self::DataDriven => 'Data-Driven',
            self::StoryCaseStudy => 'Story/Case Study',
            self::ExpertAnalysis => 'Expert Analysis',
            self::CommonMistakes => 'Common Mistakes',
            self::Listicle => 'Listicle',
            self::Comparison => 'Comparison',
            self::FaqDriven => 'FAQ-Driven',
        };
    }
}
