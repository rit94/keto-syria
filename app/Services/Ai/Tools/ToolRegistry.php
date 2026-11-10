<?php

namespace App\Services\Ai\Tools;

use App\Models\CoachConversation;
use App\Models\User;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The coach's tool set. The list and its order are fixed (sorted by name) so the request
 * prefix – and with it the prompt cache – is identical on every call and for every user.
 */
class ToolRegistry
{
    /** @var list<class-string<CoachTool>> */
    public const TOOLS = [
        CalculateNutritionTool::class,
        GetDailySummaryTool::class,
        GetFoodTool::class,
        GetMealPlanTool::class,
        GetProfileTool::class,
        GetRecipeTool::class,
        GetWeightTrendTool::class,
        ListRecipesTool::class,
        ProposeDeleteLogEntryTool::class,
        ProposeLogMealTool::class,
        ProposeMealPlanTool::class,
        ProposeSaveRecipeTool::class,
        ProposeUpdateLogEntryTool::class,
        RequestCustomFoodTool::class,
        SearchFoodsTool::class,
    ];

    /** @var array<string, CoachTool>|null */
    private ?array $tools = null;

    public function __construct(private readonly Container $container) {}

    /** @return array<string, CoachTool> keyed and sorted by name */
    public function all(): array
    {
        if ($this->tools === null) {
            $tools = [];
            foreach (self::TOOLS as $class) {
                $tool = $this->container->make($class);
                $tools[$tool->name()] = $tool;
            }
            ksort($tools);
            $this->tools = $tools;
        }

        return $this->tools;
    }

    /** @return list<array{name: string, description: string, input_schema: array<string, mixed>}> the API `tools` array */
    public function definitions(): array
    {
        return array_values(array_map(fn (CoachTool $tool) => [
            'name' => $tool->name(),
            'description' => $tool->description(),
            'input_schema' => $tool->inputSchema(),
        ], $this->all()));
    }

    /**
     * Run a tool call from the model. Never throws: every failure becomes a structured error
     * the model can read, so a tool_use always gets its tool_result.
     *
     * @param  array<string, mixed>  $input
     * @return array{result: array<string, mixed>, is_error: bool}
     */
    public function execute(User $user, string $name, array $input, ?CoachConversation $conversation = null): array
    {
        $tool = $this->all()[$name] ?? null;

        if ($tool === null) {
            return ['result' => ['error' => ['code' => 'unknown_tool', 'message' => "There is no tool named [$name]."]], 'is_error' => true];
        }

        try {
            $result = $tool->handle($user, $input, $conversation);
        } catch (Throwable $e) {
            Log::error('ai.tool_failed', ['tool' => $name, 'exception' => $e::class, 'message' => $e->getMessage()]);

            $result = ['error' => ['code' => 'tool_failed', 'message' => 'The tool failed unexpectedly. Tell the user it did not work; do not guess the result.']];
        }

        return ['result' => $result, 'is_error' => isset($result['error'])];
    }
}
