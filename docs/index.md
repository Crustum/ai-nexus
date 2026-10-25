# CakePHP AiNexus Plugin

- [Introduction](#introduction)
- [Installation](#installation)
    - [Configuration](#configuration)
    - [Access Control](#access-control)
- [What You Get](#what-you-get)
- [Discovery](#discovery)
- [Chat Playground](#chat-playground)
    - [Streaming](#streaming)
- [History](#history)
- [Commands](#commands)
- [Runtimes](#runtimes)
- [Testing](#testing)

<a name="introduction"></a>
## Introduction

*See every connection your AI agents make.*

The [CakePHP AiNexus Plugin](https://github.com/crustum/ai-nexus) is a development dashboard for AI agents built with the CakePHP AI Plugin (`crustum/cakephp-ai`) — Telescope and Horizon, but for agents. It finds the agents in your app, lets you chat with them in the browser, and shows exactly what happened underneath: every tool call with its arguments and results, every token, every error with its details.

Building an agent is a tight loop — change the prompt, run it, check the tool calls, repeat. AiNexus is that loop in a browser instead of `bin/cake console` and `debug()`.

AiNexus reads your existing `config/ai.php`. You don't configure providers or API keys in AiNexus; if your agents run in your app, they run here and vice versa.

<a name="installation"></a>
## Installation

You can install the CakePHP AiNexus Plugin via Composer:

```shell
composer require crustum/ai-nexus
```

> [!NOTE]
> This plugin should be registered in your `config/plugins.php` file.

```shell
bin/cake plugin load Crustum/AiNexus
```

> [!TIP]
> **After the plugin registers itself**, it's recommended to install the configuration with the manifest system:

```shell
bin/cake manifest install --plugin Crustum/AiNexus
```

The manifest copies the `config/ai_nexus.php` configuration file into your application (without overwriting your edits on re-runs), copies the migrations, and publishes the compiled frontend assets. Then run the migrations to create the `ai_nexus_conversations`, `ai_nexus_messages`, and `ai_nexus_tool_invocations` tables that the dashboard uses to persist playground conversations:

```shell
bin/cake migrations migrate
```

Then open `/ai_nexus` (or your configured path).

Alternatively, you can load the plugin in your `Application.php`:

```php
// In src/Application.php
public function bootstrap(): void
{
    parent::bootstrap();

    $this->addPlugin('Crustum/AiNexus');
}
```

<a name="configuration"></a>
### Configuration

Everything is optional; the defaults work. You may define the dashboard settings in your application's `config/ai_nexus.php` configuration file or as environment variables in your application's `.env` file:

| Key | Env | Default | What it does |
|---|---|---|---|
| `enabled` | `AI_NEXUS_ENABLED` | `true` except in production | Master switch, reported by `ai_nexus status`. |
| `ui.path` | `AI_NEXUS_PATH` | `ai_nexus` | Where the dashboard is mounted. |
| `ui.middleware` | — | `[]` | Middleware on every dashboard route. Your authentication and authorization live here. |
| `assets.path` | `AI_NEXUS_ASSETS_PATH` | `ai_nexus-frontend` | Webroot path the compiled frontend is published to. |
| `assets.dir` | `AI_NEXUS_ASSETS_DIR` | `null` | Override directory for the published frontend assets. |
| `discovery.paths` | — | `[]` | Directories to scan for agent classes. **An agent outside these is invisible — the usual reason the Discovery page is empty.** |
| `discovery.ignore` | — | `[]` | Agent classes to hide. |
| `playground.models` | — | `[]` | Extra models in the composer's selector, on top of the agent's own and its provider's cheapest/smartest. |
| `storage.connection` | `AI_NEXUS_DB_CONNECTION` | app default | Database connection for the dashboard tables. |
| `storage.attachments_disk` | `AI_NEXUS_ATTACHMENTS_DISK` | `local` | Disk for files uploaded in the playground. |
| `retention.auto_prune` | `AI_NEXUS_AUTO_PRUNE` | `false` | Prune old conversations automatically. |
| `retention.days` | `AI_NEXUS_PRUNE_DAYS` | `7` | How long conversations live when pruning. |

To make agents discoverable, point `discovery.paths` at the directories holding your agent classes:

```php
return [
    'AiNexus' => [
        'discovery' => [
            'paths' => [APP . 'Ai' . DS . 'Agents'],
            'ignore' => [],
        ],
    ],
];
```

You never run `npm`. Compiled assets ship inside the package and are published by the manifest, so a `composer update` can never leave you on stale assets.

<a name="access-control"></a>
### Access Control

> [!WARNING]
> **AiNexus exposes an endpoint that invokes your real agents.** It spends real API credits and executes your real tools, which may write to your database, call webhooks, or do anything else your tool code does.

Access control belongs to the host application, which applies its own middleware — for example authentication and authorization checks — through the `AiNexus.ui.middleware` configuration key:

```php
return [
    'AiNexus' => [
        'ui' => [
            'middleware' => [
                // Your authentication / authorization middleware...
            ],
        ],
    ],
];
```

The `enabled` flag defaults to `false` in production (`AI_NEXUS_ENABLED` overrides it). In `local`, the dashboard is open and needs no authentication — that's the point of a dev tool.

<a name="what-you-get"></a>
## What You Get

Discovery finds every agent class in your configured paths, along with its provider, model and tools, so writing a class is enough for it to appear after a refresh. The chat playground streams replies with attachment support and a model selector for trying another model without touching your code, while every conversation is persisted. Each tool call is inspected inline as a card carrying its arguments, results and duration, with failures marked as failures and provider-native tools distinguished from your own. An info panel shows the agent's resolved config, system prompt, tool schemas and middleware exactly as the SDK would use them. Past conversations can be filtered, replayed, renamed and deleted. Anything that throws becomes an inline card with the exception class and message, so there is never a dead stream or a raw 500.

<a name="discovery"></a>
## Discovery

Discovery scans the directories in `AiNexus.discovery.paths` for instantiable agent implementations, skipping anything listed in `AiNexus.discovery.ignore`. Each discovered agent reports its slug, provider, model, tools, and whether it can currently be built through the application container.

Debugging starts outside the UI: `ai_nexus status` prints the dashboard configuration and every discovered agent, including the failure reason for agents that cannot be instantiated — so a broken constructor or a misconfigured path shows up as text instead of a silent empty page:

```shell
bin/cake ai_nexus status
```

<a name="chat-playground"></a>
## Chat Playground

The playground sends a message to an agent and streams the answer back as it is produced. Uploads are moved to the configured attachments disk before the stream opens. The turn itself runs while the stream is read, and failures arrive as error parts of the stream rather than status codes, so the conversation never dies silently.

The composer accepts an optional model override for trying another model without touching your code. The override stays deliberately unconstrained: trying a model the dashboard never heard of surfaces the provider's rejection rather than a guess made by the dashboard. Extra models for the selector may be configured via `AiNexus.playground.models`.

<a name="streaming"></a>
### Streaming

Replies stream as the model produces them, which needs PHP to be serving HTTP directly. The dashboard detects non-streaming SAPIs and reports it in the playground instead of leaving you looking at a blank thread:

```php
use Crustum\AiNexus\AiNexus;

AiNexus::streams(); // false under cli, phpdbg and embed runtimes...
```

<a name="history"></a>
## History

Playground conversations persist in the dashboard tables. The history list is filtered, sorted, and paginated in one request, and each conversation may be replayed turn by turn with its token counts, renamed, or deleted with its messages, tool rows, and attachment files.

<a name="commands"></a>
## Commands

| Command | What it does |
|---|---|
| `ai_nexus status` | Reports dashboard configuration and every discovered agent, with failure reasons for unavailable agents. |
| `ai_nexus prune` | Deletes conversations older than `--days` (defaults to `AiNexus.retention.days`), with their attachments. |
| `ai_nexus clear` | Deletes **all** dashboard conversations and attachments. |

```shell
bin/cake ai_nexus prune
bin/cake ai_nexus prune --days 30
bin/cake ai_nexus clear
```

There is deliberately no clear-all route: the clear command does that, and the dashboard has no control for it.

<a name="runtimes"></a>
## Runtimes

The playground streams replies as the model produces them, which needs PHP to be serving HTTP directly. Under runtimes where output cannot be pushed to a client mid-request, replies arrive complete instead of streaming. Nothing breaks — the dashboard detects it and says so in the playground.

<a name="testing"></a>
## Testing

Backend tests use Pest. The default suite covers unit, feature, and integration tests:

```shell
composer test
```

```shell
composer test:unit
composer test:feature
composer test:integration
```

Browser end-to-end tests need Playwright and are not run in CI:

```shell
composer test:e2e
```
