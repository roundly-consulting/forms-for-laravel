<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Actions;

use Illuminate\Contracts\Database\Eloquent\Builder as BuilderContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\MultipleRecordsFoundException;
use RoundlyConsulting\Forms\Exceptions\FormNotFoundException;
use RoundlyConsulting\Forms\Exceptions\MultipleFormsFoundException;
use RoundlyConsulting\Forms\Models\Field;
use RoundlyConsulting\Forms\Models\Form;
use RoundlyConsulting\Forms\Models\Group;
use RoundlyConsulting\Forms\Support\FormModel;

final class FindFormAction
{
    public function execute(string $key): Form
    {
        try {
            /** @var Form $form */
            $form = $this->newFormsQuery()
                ->forKey($key)
                ->with(['groups' => function (BuilderContract $groups): void {
                    /** @var Builder<Group> $groups */
                    $groups
                        ->ordered()
                        ->with(['fields' => function (BuilderContract $fields): void {
                            /** @var Builder<Field> $fields */
                            $fields->ordered();
                        }]);
                }])
                ->sole();
        } catch (ModelNotFoundException) {
            throw FormNotFoundException::forKey($key);
        } catch (MultipleRecordsFoundException) {
            throw MultipleFormsFoundException::forKey($key);
        }

        return $form;
    }

    /** @return Builder<Form> */
    private function newFormsQuery(): Builder
    {
        $form = FormModel::class();

        return $form::query();
    }
}
