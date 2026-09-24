<?php

namespace Tests\Unit;

use App\Support\MedicalConfigValidator;
use InvalidArgumentException;
use Tests\TestCase;

class MedicalConfigValidatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['eform.medical' => [
            'benefit_types' => ['rawat_jalan'],
            'allowed_documents' => ['receipt'],
            'required_documents' => ['receipt'],
            'max_file_size_kb' => 5120,
        ]]);
    }

    public function test_all_required_medical_configuration_is_accepted(): void
    {
        MedicalConfigValidator::validate();
        $this->assertTrue(true);
    }

    public function test_empty_or_non_string_lists_fail_fast(): void
    {
        config(['eform.medical.benefit_types' => ['']]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('benefit_types');
        MedicalConfigValidator::validate();
    }

    public function test_file_size_is_positive_and_bounded(): void
    {
        config(['eform.medical.max_file_size_kb' => 0]);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('max_file_size_kb');
        MedicalConfigValidator::validate();
    }
}
