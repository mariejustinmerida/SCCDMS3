@startuml
actor USER
participant "Web Interface" as UI
participant "AI Generation" as AI
participant "Document Management System" as DMS
participant "Google API" as GAPI
database Database as DB

USER -> UI: Create New Document
USER -> UI: Load Templates (Predefined/Custom)
UI --> USER: Templates Loaded
USER -> UI: Select Template / Upload
USER -> UI: Apply Template
UI -> DMS: Process Template
DMS -> GAPI: Apply Template
GAPI --> DMS: Template Applied
DMS --> UI: Template Applied
UI --> USER: Display Document

USER -> UI: Generate Document with AI
UI -> AI: Request Content Generation
AI -> GAPI: Generate Content
GAPI --> AI: Content Generated
AI --> UI: Return AI-Generated Content
UI --> USER: Display AI Document

USER -> UI: Manage Metadata
UI -> DMS: Store Metadata
DMS -> DB: Save Metadata
DB --> DMS: Metadata Saved
DMS --> UI: Metadata Saved
UI --> USER: Metadata Confirmation

USER -> UI: Add Attachment
UI -> DMS: Process Attachment
DMS -> DB: Save Attachment
DB --> DMS: Attachment Saved
DMS --> UI: Confirmation
UI --> USER: Attachment Saved

USER -> UI: Save as Draft
UI -> DMS: Process Draft
DMS -> DB: Store Draft Document
DB --> DMS: Draft Saved
DMS --> UI: Draft Confirmation
UI --> USER: Draft Saved Confirmation

USER -> UI: Submit Document
UI -> DMS: Validate Document
DMS -> DB: Check Workflow Rules
DB --> DMS: Validation Results

alt Valid Document
    DMS -> DB: Save Final Document
    DMS -> DB: Create Workflow Records
    DB --> DMS: Document Saved
    DMS --> UI: Success Message
    UI --> USER: Document Submitted Successfully
else Invalid Document
    DMS --> UI: Validation Errors
    UI --> USER: Display Errors
    USER -> UI: Fix Issues
end

USER -> UI: Define Workflow Recipients
UI -> DMS: Set Document Routing
DMS -> DB: Store Recipient List
DB --> DMS: Recipients Saved
DMS --> UI: Routing Confirmation
UI --> USER: Workflow Setup Complete

@enduml 