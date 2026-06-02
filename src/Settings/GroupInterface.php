<?php

declare(strict_types=1);

namespace RhBlueprint\Core\Settings;

/**
 * Eine Gruppe zusammengehöriger Settings-Felder, die einem Tab zugeordnet ist.
 * Plugins implementieren das Interface und melden ihre Gruppen über
 * `rh_blueprint()->settings()->registerGroup()` an.
 */
interface GroupInterface
{
    public function id(): string;

    public function tab(): string;

    public function title(): string;

    public function description(): string;

    /**
     * @return array<int, SettingField>
     */
    public function fields(): array;
}
