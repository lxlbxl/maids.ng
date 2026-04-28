<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:7vF6UuU+S9w8G+A6oX+yvTzYvV+oZ+L+X+yvTzYvV+o=']);
    }

    protected function setupRoles()
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'employer']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'maid']);
    }
}
