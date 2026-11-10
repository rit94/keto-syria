<?php

namespace App\Services\Ai\Tools;

use App\Models\CoachConversation;
use App\Models\User;
use App\Services\Ai\Proposals\ProposalInvalidException;
use Illuminate\Support\Facades\Validator;

abstract class AbstractTool implements CoachTool
{
    /** @return array<string, mixed> Laravel validation rules for the input */
    abstract protected function rules(): array;

    /**
     * @param  array<string, mixed>  $data  validated input
     * @return array<string, mixed>
     */
    abstract protected function run(User $user, array $data, ?CoachConversation $conversation): array;

    final public function handle(User $user, array $input, ?CoachConversation $conversation = null): array
    {
        $validator = Validator::make($input, $this->rules());

        if ($validator->fails()) {
            return self::error('invalid_input', 'The tool input is invalid.', ['fields' => $validator->errors()->toArray()]);
        }

        try {
            return $this->run($user, $validator->validated(), $conversation);
        } catch (ProposalInvalidException $e) {
            return self::error('not_calculable', 'This cannot be calculated or saved as given. Fix the listed fields (or ask the user) and try again.', [
                'errors' => $e->errors,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected static function error(string $code, string $message, array $extra = []): array
    {
        return ['error' => ['code' => $code, 'message' => $message] + $extra];
    }

    /**
     * @param  array<string, mixed>  $properties
     * @param  list<string>  $required
     * @return array<string, mixed>
     */
    protected static function schema(array $properties, array $required = []): array
    {
        return [
            'type' => 'object',
            // an empty PHP array would serialise as [] – the API needs an object
            'properties' => $properties === [] ? new \stdClass : $properties,
            'required' => $required,
            'additionalProperties' => false,
        ];
    }

    /** Schema fragment shared by every tool that takes food amounts. */
    protected static function foodAmountSchema(bool $withCooked = true): array
    {
        $properties = [
            'food_id' => ['type' => 'integer', 'description' => 'Id from search_foods / get_food.'],
            'quantity' => ['type' => 'number', 'description' => 'Amount in the given unit, greater than zero.'],
            'unit' => ['type' => 'string', 'description' => 'g, kg, oz, ouqiya (Syrian, 200 g) or one of the unit keys listed for this food (e.g. piece, tbsp, cup).'],
        ];

        if ($withCooked) {
            $properties['weighed_cooked'] = [
                'type' => 'boolean',
                'description' => 'True only when the quantity is a weight measured AFTER cooking and the food is a raw entry with a cooked_yield. Omit otherwise.',
            ];
        }

        return ['type' => 'object', 'properties' => $properties, 'required' => ['food_id', 'quantity', 'unit'], 'additionalProperties' => false];
    }
}
