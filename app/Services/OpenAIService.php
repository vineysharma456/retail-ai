<?php

namespace App\Services;

use OpenAI;
use Exception;
use App\Tools\GetOrder;
use App\Tools\GetProduct;
use App\Tools\SearchProducts;
use App\Tools\EvaluateReturn;
use OpenAI\Exceptions\RateLimitException;

class OpenAIService
{
    protected $client;

    /*
    |--------------------------------------------------------------------------
    | Persistent Conversation Memory
    |--------------------------------------------------------------------------
    */
    protected array $messages = [];

    public function __construct()
{
    $this->client = OpenAI::client(
        env('OPENAI_API_KEY')
    );

    /*
    |--------------------------------------------------------------------------
    | System Prompt Stored ONCE
    |--------------------------------------------------------------------------
    */
    $this->messages[] = [

        'role' => 'system',

        'content' => <<<PROMPT
            You are a Retail AI Assistant operating as an intelligent orchestration agent for a fashion retail business.

            Your responsibilities:
            1. Personal Shopper (Revenue Agent)
            2. Customer Support Assistant (Operations Agent)

            You MUST use tools for all factual decisions.

            ==================================================
            🛍️ PERSONAL SHOPPER
            ==================================================

            Your role is to recommend products using factual inventory data.

            MANDATORY TOOL USAGE:
            - ALWAYS call search_products before making recommendations
            - NEVER recommend products without tool results
            - NEVER invent products, tags, prices, stock, vendors, or availability

            --------------------------------------------------
            SHOPPING CONSTRAINT EXTRACTION
            --------------------------------------------------

            Extract and reason about:
            - budget
            - size
            - tags/styles
            - sale preference
            - occasion
            - fit/style language

            Examples:
            - "evening gown"
            - "minimal bridal"
            - "formal modest"
            - "fitted cocktail"

            --------------------------------------------------
            SIZE + STOCK RULES
            --------------------------------------------------

            A product ONLY qualifies if:
            - requested size exists
            AND
            - stock for that size > 0

            Never recommend out-of-stock sizes.

            Always confirm:
            - requested size
            - stock quantity

            --------------------------------------------------
            TAG MATCHING RULES
            --------------------------------------------------

            Users may describe styles differently than inventory tags.

            You may intelligently SEARCH broadly, but:
            - NEVER CLAIM a tag match unless the exact tag exists in tool data
            - ONLY use tags returned by tools
            - NEVER invent aesthetics or inferred styles

            Examples:
            - "minimal" is NOT the same as:
            - modest
            - fitted
            - flowy
            unless explicitly tagged

            --------------------------------------------------
            EXACT vs PARTIAL MATCH LOGIC
            --------------------------------------------------

            EXACT MATCH:
            - All important requested tags exist in product tool data

            PARTIAL MATCH:
            - One or more requested tags are missing

            If partial:
            - explicitly state which requested tag is missing
            - explain why the product is still reasonably close

            GOOD:
            "Includes the modest tag but does not include the evening tag, so this is a partial match."

            BAD:
            "This is a perfect evening gown."
            (when evening tag does not exist)

            NEVER describe a product as:
            - perfect
            - exact
            - ideal
            unless the tags truly exist.

            --------------------------------------------------
            FALLBACK SEARCH LOGIC
            --------------------------------------------------

            If no exact matches exist:

            Step 1:
            - relax secondary tags

            Step 2:
            - relax sale preference

            Step 3:
            - provide strongest partial matches

            When fallback logic is used:
            - clearly explain it
            - explain which constraint was relaxed

            --------------------------------------------------
            SHOPPING RANKING PRIORITIES
            --------------------------------------------------

            Rank recommendations using:

            1. Exact tag match
            2. Size availability
            3. Stock availability
            4. Budget fit
            5. Sale preference
            6. Bestseller score
            7. Avoid clearance products unless necessary

            --------------------------------------------------
            RECOMMENDATION RESPONSE FORMAT
            --------------------------------------------------

            Use structured professional formatting.

            FORMAT:

            SHOPPING RECOMMENDATION

            Customer Request:
            - summarize constraints briefly

            Top Recommendation:
            - title
            - vendor
            - price
            - sale status
            - size stock
            - exact vs partial match
            - bestseller score
            - concise reasoning

            Alternative Options:
            - concise bullet list
            - explain tradeoffs

            Recommendation Summary:
            - strongest style match
            - best value
            - most popular

            --------------------------------------------------
            SHOPPING STYLE RULES
            --------------------------------------------------

            DO:
            - sound concise and premium
            - explain tradeoffs clearly
            - mention exact vs partial matches
            - explain fallback logic briefly

            DO NOT:
            - repeat the same reasoning
            - use overly conversational language
            - generate huge paragraphs
            - hallucinate missing tags

            ==================================================
            📦 SUPPORT ASSISTANT
            ==================================================

            Your role is to handle:
            - returns
            - exchanges
            - policy reasoning
            - order support

            MANDATORY TOOL FLOW:
            1. ALWAYS call get_order first
            2. THEN call evaluate_return
            3. NEVER answer policy questions from memory
            4. NEVER fabricate eligibility decisions

            ==================================================
            RETURN POLICY RULES
            ==================================================

            NORMAL ITEMS:
            - 14-day return window
            - full refund

            SALE ITEMS:
            - 7-day return window
            - store credit only

            CLEARANCE ITEMS:
            - final sale
            - no returns 

            VENDOR EXCEPTIONS:
            - Aurelia → exchange only
            - Nocturne → 21-day return window
            
            EXCHANGE RULES
            - Size exchanges allowed if stock available
            - Customer pays return shipping unless defective

            If multiple rules apply:
            - explain which rule overrides others

            ==================================================
            SUPPORT RESPONSE FORMAT
            ==================================================

            RETURN DECISION

            Order Details:
            - order id
            - product
            - vendor
            - purchase timing

            Policy Evaluation:
            - eligibility
            - return window
            - applicable policy
            - rule precedence

            Decision:
            - approved / denied
            - refund / exchange / store credit

            Next Steps:
            - concise guidance

            ==================================================
            ERROR HANDLING
            ==================================================

            If order not found:
            "I couldn’t find this order in the system. Please verify the order ID."

            If product not found:
            "I couldn’t find this product in the inventory."

            If no recommendations exist:
            - explain clearly
            - offer closest alternatives
            - explain fallback logic

            ==================================================
            🔄 CONVERSATION MEMORY
            ==================================================

            Maintain conversation continuity across turns.

            Reuse:
            - order IDs
            - product references
            - customer preferences
            - previous recommendations

            Treat messages like:
            - yes
            - proceed
            - show more
            - exchange instead
            as continuation requests.

            Do NOT repeatedly ask for information already known.

            ==================================================
            🔁 MULTI-STEP TOOL REASONING
            ==================================================

            You may call multiple tools sequentially when needed.

            Examples:
            - get_order → evaluate_return
            - search_products → get_product
            - return approval → recommend exchanges

            Continue tool calling until enough information exists.

            ==================================================
            🚫 GLOBAL SAFETY RULES
            ==================================================

            - Tool outputs are the source of truth
            - NEVER hallucinate inventory
            - NEVER hallucinate stock
            - NEVER hallucinate pricing
            - NEVER hallucinate policies
            - NEVER hallucinate tags
            - NEVER override deterministic tool decisions

            You are an orchestration and reasoning layer:
            - tools retrieve facts
            - policies enforce rules
            - your job is reasoning and explanation

            ==================================================
            OUTPUT QUALITY RULES
            ==================================================

            - Keep responses concise
            - Use sections and bullet points
            - Avoid repetition
            - Clearly separate:
            - recommendation
            - reasoning
            - decision
            - summary
            - Sound like a premium retail assistant
            - Prioritize clarity and business reasoning
            PROMPT
                ];
}

    /**
     * Main AI chat handler
     */
    public function chat(string $userMessage): string
    {
        try {

            /*
            |--------------------------------------------------------------------------
            | Store User Message In Persistent Memory
            |--------------------------------------------------------------------------
            */
            $this->messages[] = [

                'role' => 'user',

                'content' => $userMessage
            ];

            /*
            |--------------------------------------------------------------------------
            | Recursive Tool Calling Loop
            |--------------------------------------------------------------------------
            */
            $maxIterations = 5;

            $iteration = 0;

            
            while (true) {

    $response = $this->client->chat()->create([

        'model' => 'gpt-4.1-mini',

        'messages' => $this->messages,

        'tools' => $this->getTools(),
    ]);

    $message = $response->choices[0]->message;

    /*
    |--------------------------------------------------------------------------
    | Assistant normal response
    |--------------------------------------------------------------------------
    */
    $assistantMessage = [
        'role' => 'assistant',
        'content' => $message->content ?? ''
    ];

    /*
    |--------------------------------------------------------------------------
    | ONLY add tool_calls if they exist
    |--------------------------------------------------------------------------
    */
    if (!empty($message->toolCalls)) {

        $assistantMessage['tool_calls'] = [];

        foreach ($message->toolCalls as $toolCall) {

            $assistantMessage['tool_calls'][] = [

                'id' => $toolCall->id,

                'type' => 'function',

                'function' => [

                    'name' => $toolCall->function->name,

                    'arguments' => $toolCall->function->arguments
                ]
            ];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Save assistant message
    |--------------------------------------------------------------------------
    */
    $this->messages[] = $assistantMessage;

    /*
    |--------------------------------------------------------------------------
    | Stop if no tool calls
    |--------------------------------------------------------------------------
    */
    if (empty($message->toolCalls)) {

        return $message->content;
    }

    /*
    |--------------------------------------------------------------------------
    | Execute tool calls
    |--------------------------------------------------------------------------
    */
    foreach ($message->toolCalls as $toolCall) {

        $toolName = $toolCall->function->name;

        $arguments = json_decode(
            $toolCall->function->arguments,
            true
        );

        echo "\n";
        echo "=============================\n";
        echo " TOOL CALLED: {$toolName}\n";
        echo "=============================\n";

        echo "Arguments:\n";
        echo json_encode($arguments, JSON_PRETTY_PRINT);
        echo "\n\n";
        $toolResult = $this->executeTool(
            $toolName,
            $arguments
        );
        echo "Tool Result:\n";
        echo json_encode($toolResult, JSON_PRETTY_PRINT);
        echo "\n\n";

        /*
        |--------------------------------------------------------------------------
        | Add tool response
        |--------------------------------------------------------------------------
        */
        $this->messages[] = [

            'role' => 'tool',

            'tool_call_id' => $toolCall->id,

            'content' => json_encode($toolResult)
        ];
    }
}

            /*
            |--------------------------------------------------------------------------
            | Safety Fallback
            |--------------------------------------------------------------------------
            */
            return 'Maximum tool iterations reached.';

        } catch (RateLimitException $e) {

            return 'AI service is currently busy. Please try again in a moment.';

        } catch (Exception $e) {

            return 'Something went wrong: ' . $e->getMessage();
        }
    }

    /**
     * Define Available Tools
     */
    protected function getTools(): array
    {
        return [

            /*
            |--------------------------------------------------------------------------
            | search_products
            |--------------------------------------------------------------------------
            */
            [
                'type' => 'function',

                'function' => [

                    'name' => 'search_products',

                    'description' => 'Search products using filters like size, price, tags, and sale preference.',

                    'parameters' => [

                        'type' => 'object',

                        'properties' => [

                            'size' => [
                                'type' => 'string',
                                'description' => 'Requested clothing size'
                            ],

                            'max_price' => [
                                'type' => 'number',
                                'description' => 'Maximum budget'
                            ],

                            'sale_only' => [
                                'type' => 'boolean',
                                'description' => 'Only include sale items'
                            ],

                            'tags' => [
                                'type' => 'array',

                                'items' => [
                                    'type' => 'string'
                                ],

                                'description' => 'Product tags/preferences'
                            ]
                        ]
                    ]
                ]
            ],

            /*
            |--------------------------------------------------------------------------
            | get_product
            |--------------------------------------------------------------------------
            */
            [
                'type' => 'function',

                'function' => [

                    'name' => 'get_product',

                    'description' => 'Get product details by product ID.',

                    'parameters' => [

                        'type' => 'object',

                        'properties' => [

                            'product_id' => [
                                'type' => 'string'
                            ]
                        ],

                        'required' => ['product_id']
                    ]
                ]
            ],

            /*
            |--------------------------------------------------------------------------
            | get_order
            |--------------------------------------------------------------------------
            */
            [
                'type' => 'function',

                'function' => [

                    'name' => 'get_order',

                    'description' => 'Get order details using order ID.',

                    'parameters' => [

                        'type' => 'object',

                        'properties' => [

                            'order_id' => [
                                'type' => 'string'
                            ]
                        ],

                        'required' => ['order_id']
                    ]
                ]
            ],

            /*
            |--------------------------------------------------------------------------
            | evaluate_return
            |--------------------------------------------------------------------------
            */
            [
                'type' => 'function',

                'function' => [

                    'name' => 'evaluate_return',

                    'description' => 'Evaluate whether an order qualifies for return, exchange, or refund.',

                    'parameters' => [

                        'type' => 'object',

                        'properties' => [

                            'order_id' => [
                                'type' => 'string'
                            ]
                        ],

                        'required' => ['order_id']
                    ]
                ]
            ]
        ];
    }

    /**
     * Execute Tools Dynamically
     */
    protected function executeTool(
        string $toolName,
        array $arguments = []
    )
    {
        switch ($toolName) {

            case 'search_products':

                return (new SearchProducts())
                    ->execute($arguments);

            case 'get_product':

                return (new GetProduct())
                    ->execute(
                        $arguments['product_id']
                    );

            case 'get_order':

                return (new GetOrder())
                    ->execute(
                        $arguments['order_id']
                    );

            case 'evaluate_return':

                return (new EvaluateReturn())
                    ->execute(
                        $arguments['order_id']
                    );

            default:

                return [
                    'error' => 'Unknown tool.'
                ];
        }
    }
}