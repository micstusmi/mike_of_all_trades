# Mike's AI Estimator v1.0 — Software Design Specification

## Purpose

Mike's AI Estimator is a reusable AI quoting engine for Mike of All Trades.

The first supported service is painting. Later services may include handyman work, bathrooms, kitchens, signage, IT, web design and property maintenance.

## Core Principle

The AI does not replace Mike.

The AI prepares the estimate, collects information, analyses uploads, builds a structured project understanding, and suggests pricing assumptions.

Mike reviews and approves the final quote before it becomes official.

## Core Flow

Customer opens AI Estimator.

Conversation is created automatically.

Customer can:
- answer questions,
- describe the job,
- upload plans,
- upload photos,
- upload PDFs,
- upload existing quotes.

AI builds a Project Brain.

Pricing engine calculates estimate.

Mike reviews.

Zoho quote is created and emailed.

## Main Modules

1. Conversation Engine
2. Upload Engine
3. Project Brain
4. Vision Analysis
5. Painting Knowledge Engine
6. Pricing Engine
7. Zoho Engine
8. Admin Review Dashboard
9. Opportunity Finder

## Database Tables

- ai_conversations
- ai_messages
- ai_answers
- ai_uploads
- ai_analysis
- ai_quotes

## Project Brain

The Project Brain is the structured understanding of the job.

Example fields:

- customer_type
- service
- property_type
- new_or_repaint
- interior_or_exterior
- surfaces
- measurements
- repairs
- plaster_repairs
- access
- paint_supply
- uploaded_files
- AI_confidence
- pricing_assumptions

## Painting v1 Questions

The painting estimator should determine:

- customer type
- interior / exterior / both
- plans/photos available
- square metres known or unknown
- new build / repaint / renovation
- undercoat / top coats / unsure
- walls / ceilings / trims / doors / windows
- surface condition
- plaster repairs required
- access issues
- paint supplied by customer or Mike
- extra work opportunities

## Payment Terms

If Mike supplies paint or materials, materials must be paid for upfront before ordering.

If a job is ongoing for more than one week, weekly progress payments are required.

## AI Opportunity Finder

The AI may suggest relevant extra services, such as:

- plaster repairs
- crack filling
- patching holes
- pressure washing
- silicone replacement
- deck staining
- fence painting
- gutter cleaning
- handyman repairs

## Safety Rules

AI estimates are not final quotes.

Final price may change after inspection, measurements, access review, product selection and scope confirmation.

Final quotes are reviewed by Mike before being official.

## Build Order

### Module 1
Database-backed conversation engine.

### Module 2
Upload system linked to conversations.

### Module 3
Admin review page.

### Module 4
OpenAI text reasoning.

### Module 5
OpenAI vision/photo/plan analysis.

### Module 6
Painting pricing engine connection.

### Module 7
Zoho quote creation.

### Module 8
Reusable modes for other services.
