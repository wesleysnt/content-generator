### Task 2: Role Column + Enums

**Files:**
- Create: `database/migrations/2026_09_05_000001_add_role_to_users_table.php`
- Create: `app/Enums/RequestStatus.php`, `app/Enums/VariationStatus.php`, `app/Enums/RevisionType.php`, `app/Enums/AngleType.php`
- Test: `tests/Unit/AngleTypeTest.php`

**Interfaces:**
- Produces: `RequestStatus` (Draft, Queued, Processing, Completed, Failed), `VariationStatus` (Pending, Generated, Discarded, Final), `RevisionType` (AiGeneration, AiRegeneration, SectionRegeneration, TitleRegeneration, WriterEdit, Restore), `AngleType` (10 cases with `label()`), `users.role` column

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Unit/AngleTypeTest.php

use App\Enums\AngleType;

it('has exactly ten angles', function () {
    expect(count(AngleType::cases()))->toBe(10);
});

it('has distinct labels', function () {
    $labels = array_map(fn ($a) => $a->label(), AngleType::cases());
    expect(count(array_unique($labels)))->toBe(10);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/AngleTypeTest.php`
Expected: FAIL — "Class App\Enums\AngleType not found"

- [ ] **Step 3: Write migration + enums**

```php
<?php
// database/migrations/2026_09_05_000001_add_role_to_users_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('writer')->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
```

```php
<?php
// app/Enums/RequestStatus.php

namespace App\Enums;

enum RequestStatus: string
{
    case Draft = 'draft';
    case Queued = 'queued';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
```

```php
<?php
// app/Enums/VariationStatus.php

namespace App\Enums;

enum VariationStatus: string
{
    case Pending = 'pending';
    case Generated = 'generated';
    case Discarded = 'discarded';
    case Final = 'final';
}
```

```php
<?php
// app/Enums/RevisionType.php

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
```

```php
<?php
// app/Enums/AngleType.php

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
```

- [ ] **Step 4: Run migration + tests**

```bash
php artisan migrate
php artisan test tests/Unit/AngleTypeTest.php
```

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: add role column and domain enums"
```

---

