---
name: publish-next-post
description: Find the next draft post on fabiopacifici.com, review its content, and publish it. Checks content calendar alignment before publishing.
version: 1.0.0
trigger:
  type: manual
  command: /fab publish-next
tools:
  - exec
skills:
  - fab
---

# Routine: Publish Next Post

Find the oldest draft post, verify it matches the content calendar, and publish it.

## Steps

### 1. List draft posts
Ask the platform agent:
> "List all posts that are in draft status, ordered by creation date oldest first."
Tool: `list_posts`

### 2. Check content calendar
Read `/home/pacificDev/.openclaw/workspace-marketing/strategy/content_schedule.md`
Identify which post should be published next based on the schedule.

### 3. Match draft to calendar
Compare the oldest draft title to the calendar. If it matches, proceed. If not, flag to user.

### 4. Publish
Ask the platform agent:
> "Publish post #[ID]."
Tool: `pacificdev_course` or direct admin action.

### 5. Report
Send summary to Telegram: which post was published, URL if available.

## Output Format
```
✅ Published: [Post Title]
📅 Was due: [Date from calendar]
🔗 [URL if available]
```
