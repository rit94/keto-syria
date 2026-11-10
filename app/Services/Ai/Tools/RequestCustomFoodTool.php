<?php

namespace App\Services\Ai\Tools;

use App\Models\CoachConversation;
use App\Models\User;

/**
 * The model may not invent nutrition facts, so there is no "create food" tool. When a food is
 * missing from the database this asks the UI to open the custom-ingredient form, where the
 * user types the values from the package label themselves.
 */
class RequestCustomFoodTool extends AbstractTool
{
    public const UI_ACTION = 'open_custom_food_form';

    public function name(): string
    {
        return 'request_custom_food';
    }

    public function description(): string
    {
        return 'Call this when a food the user wants is not in the database (search_foods found nothing suitable). It asks the app to open the '
            .'"add custom ingredient" form, pre-filled with the name, so the user can enter the nutrition values from the package label themselves. '
            .'It creates nothing. Never supply or estimate nutrition values for a missing food yourself.';
    }

    public function inputSchema(): array
    {
        return self::schema([
            'name' => ['type' => 'string', 'description' => 'The food name as the user said it.'],
        ], ['name']);
    }

    protected function rules(): array
    {
        return ['name' => ['required', 'string', 'min:1', 'max:120']];
    }

    protected function run(User $user, array $data, ?CoachConversation $conversation): array
    {
        $name = trim($data['name']);

        return [
            'status' => 'custom_food_form_requested',
            'created' => false,
            'instruction' => 'The app will offer the user a form to add "'.$name.'" as a custom ingredient with values from its label. '
                .'Tell the user this food is not in the database yet and that they can add it with the form; once added you can find it with search_foods. '
                .'Do not state any nutrition values for it.',
            'ui_action' => ['type' => self::UI_ACTION, 'name' => $name],
        ];
    }
}
