<?php

use App\Services\Ai\AiResponse;
use App\Services\Ai\Coach\SystemPrompt;
use App\Services\Ai\Proposals\ProposalType;
use App\Services\Ai\SmartLog\FoodDescriptionParser;
use App\Services\Ai\SmartLog\UnitAliases;
use App\Services\Ai\Tools\AbstractProposalTool;
use App\Services\Ai\Tools\ToolRegistry;

describe('UnitAliases', function () {
    it('maps spoken Arabic, dialect and English unit words to unit keys', function (string $spoken, string $key) {
        expect(UnitAliases::canonical($spoken))->toBe($key);
    })->with([
        ['غرام', 'g'], ['جرام', 'g'], ['G', 'g'], ['grams', 'g'],
        ['كيلو', 'kg'], ['KG', 'kg'],
        ['وقية', 'ouqiya'], ['أوقية', 'ouqiya'], ['وقيّة', 'ouqiya'],
        ['حبة', 'piece'], ['حبّه', 'piece'], ['pieces', 'piece'],
        ['ملعقة كبيرة', 'tbsp'], ['معلقة أكل', 'tbsp'], ['Tablespoon', 'tbsp'],
        ['ملعقة صغيرة', 'tsp'], ['كاسة', 'cup'], ['كوب', 'cup'],
        ['شريحة', 'slice'], ['كمشة', 'handful'], ['فنجان', 'coffee_cup'],
        [' piece ', 'piece'], ['tbsp', 'tbsp'],
    ]);

    it('passes unknown words through normalised, so the database decides', function () {
        expect(UnitAliases::canonical('Bucket'))->toBe('bucket')
            ->and(UnitAliases::canonical('large_piece'))->toBe('large_piece');
    });
});

describe('AiResponse', function () {
    it('reads blocks by type, not by position', function () {
        $response = AiResponse::fromBody(json_decode(json_encode([
            'id' => 'msg_1',
            'model' => 'claude-opus-5-5',
            'stop_reason' => 'tool_use',
            'content' => [
                ['type' => 'thinking', 'thinking' => '', 'signature' => 'abc'],
                ['type' => 'text', 'text' => 'أول'],
                ['type' => 'tool_use', 'id' => 'toolu_1', 'name' => 'get_profile', 'input' => new stdClass],
                ['type' => 'text', 'text' => 'تاني'],
                'junk',
            ],
            'usage' => ['input_tokens' => 7, 'output_tokens' => 3],
        ])));

        expect($response->text())->toBe("أول\n\nتاني")
            ->and($response->toolUses())->toBe([['id' => 'toolu_1', 'name' => 'get_profile', 'input' => []]])
            ->and($response->content)->toHaveCount(4)
            ->and($response->usage)->toBe(['input_tokens' => 7, 'output_tokens' => 3, 'cache_creation_input_tokens' => 0, 'cache_read_input_tokens' => 0])
            ->and($response->isRefusal())->toBeFalse()
            // an empty tool input survives re-encoding as an object
            ->and(json_encode($response->content[2]))->toContain('"input":{}');
    });

    it('recognises a refusal and its category', function () {
        $response = AiResponse::fromBody(json_decode('{"content":[],"stop_reason":"refusal","stop_details":{"type":"refusal","category":"bio"}}'));

        expect($response->isRefusal())->toBeTrue()
            ->and($response->refusalCategory)->toBe('bio')
            ->and($response->text())->toBe('');
    });
});

describe('prompts and schemas', function () {
    it('keeps the coach system prompt free of digits and per-request data', function () {
        $prompt = SystemPrompt::text();

        expect(preg_match('/\d/', $prompt))->toBe(0)
            ->and($prompt)->toBe(SystemPrompt::text())
            ->and($prompt)->toContain('[APP NOTE]', 'app_context', 'calculate_nutrition', 'search_foods', 'request_custom_food')
            ->and(strlen($prompt))->toBeGreaterThan(2500);
    });

    it('gives the parser no place to put nutrition values', function () {
        $schema = json_encode(FoodDescriptionParser::schema());
        $instructions = FoodDescriptionParser::instructions();

        foreach (['calor', 'protein', 'carb', 'kcal'] as $word) {
            expect($schema)->not->toContain($word);
        }

        expect($instructions)->toContain('Do not output calories', 'ouqiya', 'piece')
            ->and(preg_match('/\d/', $instructions))->toBe(0);
    });

    it('tells the model in plain words that a proposal is not saved', function () {
        expect(AbstractProposalTool::NOT_SAVED_NOTE)->toContain('NOTHING has been saved', 'Do not say it was logged');
    });

    it('registers the tool classes in name order and without a custom-food writer', function () {
        $names = array_map(fn (string $class) => class_basename($class), ToolRegistry::TOOLS);
        $sorted = $names;
        sort($sorted);

        expect($names)->toBe($sorted)
            ->and($names)->toHaveCount(15)
            ->and(implode(',', $names))->not->toContain('ProposeCustomFood');
    });

    it('only lets the user edit the fields that belong to each proposal type', function () {
        expect(ProposalType::LogMeal->editableKeys())->toBe(['date', 'meal', 'items'])
            ->and(ProposalType::DeleteLogEntry->editableKeys())->toBe([])
            ->and(ProposalType::UpdateLogEntry->editableKeys())->toBe(['changes']);
    });
});
