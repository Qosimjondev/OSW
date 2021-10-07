<?php

declare(strict_types=1);

namespace app\models\scenarios\Vacancy;

use Yii;
use app\models\Vacancy;

final class SetActiveScenario
{
    private Vacancy $model;
    private array $errors = [];

    public function __construct(Vacancy $model)
    {
        $this->model = $model;
    }

    public function run(): bool
    {
        if ($this->validateLanguages() && $this->validateLocation()) {
            $this->model->setActive();

            return true;
        }

        return false;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    private function validateLanguages(): bool
    {
        if (!$this->model->getLanguagesWithLevels()->count()) {
            $this->errors['languages'] = Yii::t('app', 'You must have at least one language for Vacancy');

            return false;
        }

        return true;
    }

    private function validateLocation(): bool
    {
        if (!$this->model->isRemote()) {
            if (!($this->model->location_lon && $this->model->location_lat)) {
                $this->errors['location'] = Yii::t('app', 'Location should be set');

                return false;
            }
        }

        return true;
    }
}
