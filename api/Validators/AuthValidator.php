<?php

class AuthValidator
{
    private const MIN_PASSWORD_LENGTH = 8;
    private const MAX_PASSWORD_BYTES = 4096;

    public function validateLogin(array $data): array
    {
        $errors = [];

        $this->validateRequiredString($data, 'username', $errors);
        $this->validateRequiredString($data, 'password', $errors);

        if (isset($data['remember']) && !is_bool($data['remember'])) {
            $errors['remember'] = 'Remember must be true or false.';
        }

        return $errors;
    }

    public function validateVerifyPassword(array $data): array
    {
        $errors = [];

        $this->validateRequiredString($data, 'password', $errors);

        return $errors;
    }

    public function validateChangePassword(array $data): array
    {
        $errors = [];

        $this->validateRequiredString($data, 'current_password', $errors);
        $this->validateNewPassword($data, $errors);

        return $errors;
    }

    public function validateResetPassword(array $data): array
    {
        $errors = [];

        $this->validateRequiredString($data, 'username', $errors);
        $this->validateRequiredString($data, 'reset_code', $errors);
        $this->validateNewPassword($data, $errors);

        return $errors;
    }

    private function validateNewPassword(array $data, array &$errors): void
    {
        $this->validateRequiredString($data, 'new_password', $errors);

        if (!isset($errors['new_password'])) {
            $length = strlen((string) $data['new_password']);

            if ($length < self::MIN_PASSWORD_LENGTH) {
                $errors['new_password'] = 'New password must be at least 8 characters.';
            } elseif ($length > self::MAX_PASSWORD_BYTES) {
                $errors['new_password'] = 'New password is too long.';
            }
        }

        if (
            isset($data['password_confirmation']) &&
            (string) $data['password_confirmation'] !== (string) ($data['new_password'] ?? '')
        ) {
            $errors['password_confirmation'] = 'Password confirmation does not match.';
        }
    }

    private function validateRequiredString(array $data, string $field, array &$errors): void
    {
        if (!isset($data[$field]) || trim((string) $data[$field]) === '') {
            $errors[$field] = str_replace('_', ' ', ucfirst($field)) . ' is required.';
        }
    }
}
