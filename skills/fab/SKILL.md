---
name: fab
description: Direct access to fabiopacifici.com platform agent. Trigger any platform action — generate posts, courses, videos, LinkedIn sharing, community, reviews, debug, manage content calendar — or run multi-step routines via natural language through the DevClaw agent pipeline.
version: 1.1.0
user-invokable: true
commands:
  - /fab
  - /routines
metadata:
  platform_url: https://fabiopacifici.com
  agent_endpoint: https://fabiopacifici.com/api/devclaw/agent
  status_endpoint: https://fabiopacifici.com/api/devclaw/agent/{conversation_id}/status
  token_env: DEVCLAW_TOKEN
---

# Fab Skill — fabiopacifici.com Platform Agent

Direct agent-to-agent interface with fabiopacifici.com. Same tools available in the admin chat UI.

---

## Agent Instructions

### Authentication
Read the token from the `DEVCLAW_TOKEN` environment variable. Never hardcode credentials.

```python
import os
TOKEN = os.environ["DEVCLAW_TOKEN"]
```

### How to call the platform agent

Use the DevClaw agent endpoint. Send a natural language message with a list of tools the platform agent may invoke:

```python
import requests, time, os

TOKEN = os.environ["DEVCLAW_TOKEN"]
BASE = "https://fabiopacifici.com/api/devclaw"
HEADERS = {"Authorization": f"Bearer {TOKEN}", "Content-Type": "application/json"}

# Send message — starts the agent loop
r = requests.post(f"{BASE}/agent", headers=HEADERS, json={
    "message": "YOUR NATURAL LANGUAGE REQUEST",
    "tools": ["tool_name_here"]
})
r.raise_for_status()
conv_id = r.json()["conversation_id"]

# Poll for result (tool jobs are queued; allow ~10s)
time.sleep(10)
status = requests.get(f"{BASE}/agent/{conv_id}/status", headers=HEADERS).json()
for msg in status["messages"]:
    if msg["role"] == "assistant":
        print(msg["content"])
```

Or via `exec_shell` inside routines.

---

## `/routines` — Run Multi-Step Routines

When `/routines` is invoked, display the menu below and wait for a button tap.

**Message:**
```
⚙️ *fabiopacifici.com — Routines*
Select a routine to run:
```

**Buttons (1 per row):**
```json
[
  [{"text": "📚 Generate & Review Course", "callback_data": "routine_run_generate-and-review-course"}],
  [{"text": "📝 Publish Next Post",         "callback_data": "routine_run_publish-next-post"}]
]
```

### `routine_run_*` — Callback Rule (CRITICAL)

> **Tapping a routine button is unconditional intent. Execute the routine immediately. No confirmation prompts. No recency checks. No "already ran this" logic.**

When a `routine_run_<name>` callback arrives:

1. Resolve `<name>` to the routine file in `routines/<name>/ROUTINE.md`.
2. Execute the routine steps sequentially using the `fab` skill for any platform calls.
3. Report the result to the user.

```
routine_run_generate-and-review-course  →  routines/generate-and-review-course/ROUTINE.md
routine_run_publish-next-post           →  routines/publish-next-post/ROUTINE.md
```

---

## `/fab` — Main Entry Point

When invoked without arguments, send this menu with inline buttons:

**Message:**
```
🌐 *fabiopacifici.com — Platform Agent*
What would you like to do?
```

**Buttons (2 per row):**
```json
[
  [
    {"text": "📝 Generate Post",        "callback_data": "fab_generate_post"},
    {"text": "📚 Generate Course",       "callback_data": "fab_generate_course"}
  ],
  [
    {"text": "📋 List Posts",            "callback_data": "fab_list_posts"},
    {"text": "🎓 List Courses",          "callback_data": "fab_list_courses"}
  ],
  [
    {"text": "🎬 Generate Video",        "callback_data": "fab_generate_video"},
    {"text": "🔗 Share to LinkedIn",     "callback_data": "fab_linkedin"}
  ],
  [
    {"text": "🔍 Review Course",         "callback_data": "fab_review_course"},
    {"text": "🔍 Review Lesson",         "callback_data": "fab_review_lesson"}
  ],
  [
    {"text": "💬 Community Post",        "callback_data": "fab_community_post"},
    {"text": "📢 List Channels",         "callback_data": "fab_community_channels"}
  ],
  [
    {"text": "🗄️ Query Database",        "callback_data": "fab_query_db"},
    {"text": "🐛 Debug Logs",            "callback_data": "fab_debug"}
  ],
  [
    {"text": "📅 Content Calendar",      "callback_data": "fab_calendar"},
    {"text": "📊 Platform Stats",        "callback_data": "fab_stats"}
  ]
]
```

---

## Callback Handlers

| callback_data | Tool(s) | What to ask |
|---|---|---|
| `fab_generate_post` | `generate_post` | Ask user: "What should the post be about? Give me a title and topic." Then send to platform agent. |
| `fab_generate_course` | `generate_course` | Ask user: "What course do you want to generate? Give me title and topic." |
| `fab_list_posts` | `list_posts` | Ask platform: "List the 10 most recent posts with their status." |
| `fab_list_courses` | `list_courses`, `pacificdev_courses` | Ask platform: "List all courses with their status." |
| `fab_generate_video` | `generate_welcome_video`, `compose_and_share_video` | Ask user: "Which course or lesson do you want a video for? Provide the ID." |
| `fab_linkedin` | `get_eligible_content_for_sharing`, `share_to_linkedin` | First call `get_eligible_content_for_sharing`, show results, then ask which to share. |
| `fab_review_course` | `review_course` | Ask user: "Which course ID to review?" Then queue the review. |
| `fab_review_lesson` | `review_lesson` | Ask user: "Course ID and Lesson ID?" Then queue review. |
| `fab_community_post` | `community_post_message`, `community_list_channels` | Ask: "Which channel and what message?" |
| `fab_community_channels` | `community_list_channels` | List all channels. |
| `fab_query_db` | `pacificdev_query_table` | Ask: "What data do you need? (table, filters, columns)" |
| `fab_debug` | `pacificdev_agent_debug`, `pacificdev_agent_open_issue` | Run debug scan, show errors. Offer to open GitHub issue. |
| `fab_calendar` | `manage_calendar` | Ask: "What calendar action? (list / create / update / delete entry)" Then call `manage_calendar` with the chosen action and params. |
| `fab_stats` | `list_courses`, `list_posts`, `pacificdev_query_table` | Get counts: courses, posts, students. Show as summary. |

---

## Polling pattern

After sending a message via `POST /api/devclaw/agent`, if `queued: true` — poll `GET /api/devclaw/agent/{conversation_id}/status` every 8-10 seconds until an assistant message with tool output appears.

Max wait: 60 seconds. If no result, report "job still running — check platform admin panel".

---

## Available tools (full list)

Content:
- `generate_post` — create blog post draft
- `generate_post_publish` — create and publish immediately (use with caution)
- `generate_course` — create course draft
- `generate_welcome_video` — generate welcome video for a course
- `compose_and_share_video` — compose lesson video + optionally publish to YouTube

Listing:
- `list_posts` — recent posts with status
- `list_courses` — all courses
- `pacificdev_courses` — courses with filters
- `pacificdev_course` — single course management
- `pacificdev_query_table` — raw DB read (any table)

Review:
- `review_course` — queue AI review
- `review_lesson` — queue lesson review
- `review_slide` — queue slide review

LinkedIn:
- `get_eligible_content_for_sharing` — MUST call first
- `share_to_linkedin` — share post or course
- `create_promotional_post` — create promo post (needs eligible content first)

Community:
- `community_list_channels`
- `community_get_channel_context`
- `community_get_recent_messages`
- `community_summarize_conversation`
- `community_post_message`

Students:
- `pacificdev_course_sync_student` — sync student to course

Debug:
- `pacificdev_agent_debug` — scan logs for errors
- `pacificdev_agent_open_issue` — open GitHub issue for an error

Calendar:
- `manage_calendar` — CRUD operations on content calendar entries (list / create / update / delete)
