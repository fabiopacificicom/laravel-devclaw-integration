---
name: generate-and-review-course
description: Generate a full course draft on fabiopacifici.com from a brief, then queue an AI review. Used to build new course content from workspace material.
version: 1.0.0
trigger:
  type: manual
  command: /fab new-course
tools:
  - exec
skills:
  - fab
---

# Routine: Generate and Review Course

Create a new course draft from a brief and immediately queue it for AI review.

## Steps

### 1. Get brief
If not provided, ask: "What is the course title and topic? Provide a short description (2-3 sentences)."

### 2. Generate course
Ask the platform agent:
> "Generate a course called [TITLE] about [TOPIC]. [BRIEF]."
Tool: `generate_course`

Poll until complete. Extract course ID from response.

### 3. Queue review
Ask the platform agent:
> "Review course #[ID]."
Tool: `review_course`

### 4. Report
```
📚 Course created: #[ID] — [Title]
🔍 AI review queued
⏳ Check admin panel for review results in ~2 min
```

## Notes
- Course is created as inactive (private) by default
- Review checks structure, content quality, and learning outcomes
- After review, Fabio approves and publishes from admin panel
