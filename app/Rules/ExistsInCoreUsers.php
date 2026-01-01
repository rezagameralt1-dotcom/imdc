<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

class ExistsInCoreUsers implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, \Illuminate\Translation\PotentiallyTranslatedString): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $exists = DB::connection('core')
            ->table('users')
            ->where('id', $value)
            ->exists();

        if (!$exists) {
            $fail("The {$attribute} must be a valid user ID in the core database.");
        }
    }
}
