# 🛍️ Retail AI Assistant

An AI-powered retail assistant built using Laravel and OpenAI function calling.

This project simulates two intelligent retail agent roles:

- 🛒 **Personal Shopper (Revenue Agent)** — recommends products using inventory-aware reasoning
- 📦 **Customer Support Assistant (Operations Agent)** — evaluates return eligibility using deterministic business rules

Built using a **tool-calling architecture** to ensure:
- high accuracy
- explainable reasoning
- policy-safe decisions
- hallucination prevention

---

# 🚀 Features

- ✅ AI-powered product recommendations
- ✅ Deterministic return policy evaluation
- ✅ OpenAI function calling
- ✅ Multi-step tool execution
- ✅ Inventory-aware filtering
- ✅ Conversation continuity
- ✅ Hallucination prevention
- ✅ CLI-based interactive assistant

---

# 🧠 Architecture Overview

```txt
User Input
    ↓
Laravel CLI Command
    ↓
OpenAI Orchestration Layer
    ↓
Tool Selection (Function Calling)
    ↓
Laravel Tool Execution
    ↓
Structured Tool Output
    ↓
LLM Reasoning + Final Response

🔹 Key Design Principles
The LLM handles:
reasoning
explanation
tool selection
conversational flow
Laravel tools handle:
factual retrieval
inventory validation
policy enforcement
return logic
The model never directly accesses CSV files
Tool outputs are treated as the source of truth
Deterministic business logic minimizes hallucination risk



📁 Project Structure

retail-ai-assistant/
│
├── app/
│   ├── Console/
│   │   └── Commands/
│   │       └── RetailAIChatCommand.php
│   │
│   ├── Services/
│   │   ├── OpenAIService.php
│   │   └── CsvService.php
│   │
│   └── Tools/
│       ├── SearchProducts.php
│       ├── GetProduct.php
│       ├── GetOrder.php
│       └── EvaluateReturn.php
│
├── storage/
│   └── app/
│       └── data/
│           ├── inventory.csv
│           ├── orders.csv
│           └── policies.txt
│
├── README.md
├── ARCHITECTURE.md
├── composer.json
├── .env.example
└── artisan



⚙️ Setup Instructions

1️⃣ Clone Repository
git clone https://github.com/YOUR_USERNAME/retail-ai.git
2️⃣ Move Into Project
cd retail-ai
3️⃣ Install Dependencies
composer install
4️⃣ Create Environment File
cp .env.example .env
5️⃣ Generate Laravel App Key
php artisan key:generate
6️⃣ Add OpenAI API Key

Inside .env:

OPENAI_API_KEY=your_openai_api_key_here
▶ Running The AI Assistant

Start the CLI assistant:

php artisan ai:chat
💬 Example Usage
🛍️ Shopping Scenarios
I need a modest evening dress under $300 in size 8.
Recommend a sparkle cocktail dress in size 6.
I want a bridal gown on sale in size 10.
📦 Support Scenarios
Can I return order O0092?
What if I want an exchange instead?
Recommend similar dresses in the same size.
🚫 Edge Cases
Can I return order INVALID999?
🛠️ Tools
Tool	Description
search_products(filters)	Searches and ranks products
get_product(product_id)	Retrieves product details
get_order(order_id)	Retrieves order information
evaluate_return(order_id)	Applies deterministic return policy logic
🔄 Multi-Step Tool Execution

The AI agent dynamically selects tools using OpenAI function calling.

Example support flow:

get_order(order_id)
        ↓
evaluate_return(order_id)
        ↓
Generate policy-safe response

Example shopping flow:

search_products(filters)
        ↓
Rank matching products
        ↓
Generate recommendation reasoning
🛡️ Hallucination Prevention

This system minimizes hallucinations using:

deterministic Laravel tools
OpenAI function calling
structured tool-first workflows
backend policy enforcement
strict refusal handling
exact inventory validation

The AI never invents:

stock
products
prices
orders
policies
return decisions

All factual information comes from tools only.

🛍️ Personal Shopper Logic

The recommendation engine considers:

price filtering
size validation
stock availability
tag matching
sale prioritization
bestseller ranking

Fallback strategy:

Relax strict tag filters
Relax sale preference
Return closest matching products
📦 Return Policy Logic

Deterministic business rules:

Product Type	Policy
Regular Items	14-day refund window
Sale Items	7-day store credit only
Clearance Items	Final sale / no returns
Aurelia Couture	Exchange only
Nocturne	21-day return window

Policy precedence is enforced programmatically.

💡 Why Tool-Based Architecture?

This architecture provides:

✔ Higher accuracy
✔ Lower hallucination risk
✔ Explainable reasoning
✔ Deterministic policy enforcement
✔ Better scalability
✔ Clean separation of concerns

🧰 Technologies Used
Laravel
PHP
OpenAI API
OpenAI Function Calling
CSV-based retrieval
CLI interaction
📝 Notes
No external store integrations were used
The project is fully local and simulation-based
The focus is on agent orchestration and business-safe reasoning
👨‍💻 Author

Developed as part of a Retail AI Assistant take-home assignment.
