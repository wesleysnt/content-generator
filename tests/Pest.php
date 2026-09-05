<?php

declare(strict_types=1);
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Bind Feature tests to the application's test case so the Laravel
| application (container, facades, database) is booted for each test.
|
*/

pest()->extend(TestCase::class)->in('Feature');
