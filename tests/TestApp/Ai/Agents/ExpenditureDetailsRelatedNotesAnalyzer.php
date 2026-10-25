<?php
declare(strict_types=1);

namespace TestApp\Ai\Agents;

use Crustum\Ai\Attributes\Model;
use Crustum\Ai\Attributes\Provider;

/**
 * Long-named analyzer fixture extending the weather agent.
 */
#[Provider('openai')]
#[Model('gpt-5.6-luna')]
class ExpenditureDetailsRelatedNotesAnalyzer extends WeatherAgent
{
}
