@startuml
actor User
participant System
participant "Google Services" as Google
database Database

User -> System: Access Compose Page
System -> Database: Check Authentication
Database --> System: Return Auth Status

alt Not Logged In
    System --> User: Show Login Form
    User -> System: Submit Credentials
    System -> Database: Verify Credentials
    Database --> System: Authentication Result
    System --> User: Display Compose Page
else Already Logged In
    System --> User: Display Compose Page
end

alt Not Connected to Google
    User -> System: Click Connect to Google
    System -> Google: Request Authentication
    Google --> User: Ask for Permissions
    User -> Google: Grant Permissions
    Google --> System: Return Access Token
    System -> Database: Store Google Token
else Already Connected
    System --> User: Show Google Doc Interface
end

User -> System: Select Document Creation Method

alt AI Generator
    User -> System: Enter AI Prompt
    System -> Database: Log AI Request
    System -> Google: Send AI Generation Request
    Google --> System: Return Generated Content
    Google --> User: Display in Editor
else Use Template
    User -> System: Select Template
    System -> Database: Retrieve Template Data
    System -> Google: Apply Template
    Google --> User: Display Template in Editor
else Manual Creation
    System -> Google: Create Blank Document
    Google --> User: Display Blank Document
    Database <- System: Log Document Creation
end

User -> Google: Edit Document Content
User -> System: Fill Document Metadata Form
User -> System: Define Workflow Recipients

alt Submit Document
    User -> System: Click Submit Button
    System -> System: Validate Form
    
    alt Invalid Form
        System --> User: Show Validation Errors
        User -> System: Correct Errors
    else Valid Form
        System -> Database: Store Document Metadata
        System -> Database: Create Workflow Records
        Database --> System: Confirmation
        System --> User: Show Success Message
    end
    
else Save as Draft
    User -> System: Click Save Draft
    System -> Database: Store Draft Document
    Database -> Database: Create Draft Entry
    Database --> System: Confirmation
    System --> User: Show Draft Saved Message
    
else Discard Document
    User -> System: Click Discard
    System --> User: Ask for Confirmation
    User -> System: Confirm Discard
    System -> Database: Log Discarded Document
    System --> User: Redirect to Dashboard
end

@enduml 