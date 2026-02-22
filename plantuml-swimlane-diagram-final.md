@startuml
skinparam dpi 300
skinparam handwritten false
skinparam monochrome true
skinparam packageStyle rectangle
skinparam defaultFontName Arial
skinparam defaultFontSize 12

|User|
|System|
|Google Services|
|Database|

|User|
start
:Access Compose Page;

|System|
:Check Authentication Status;
:Query User Session Data;

|Database|
:Verify User Credentials;
:Return Session Status;

|System|
if (User Logged In?) then (No)
  |User|
  :Login with Credentials;
  |System|
  :Process Login Request;
  |Database|
  :Authenticate User;
  :Update Login Timestamp;
else (Yes)
endif

|System|
:Display Compose Interface;

if (Connected to Google?) then (No)
  |User|
  :Click Connect to Google;
  |Google Services|
  :Authenticate User;
  :Grant Access Permissions;
  |System|
  :Store Google Authentication Token;
  |Database|
  :Save Google Token;
else (Yes)
endif

|User|
:Select Document Creation Method;

if (Method Selected) then (AI Generator)
  |User|
  :Enter AI Document Prompt;
  |System|
  :Process AI Request;
  |Database|
  :Log AI Request;
  |Google Services|
  :Generate Document Content;
elseif (Method Selected) then (Template)
  |User|
  :Select Document Template;
  |System|
  :Process Template Selection;
  |Database|
  :Retrieve Template Data;
  |Google Services|
  :Apply Selected Template;
else (Manual)
  |Google Services|
  :Create Blank Document;
  |Database|
  :Log New Document Creation;
endif

|Google Services|
:Render Document in Editor;

|User|
:Edit Document Content;
:Complete Document Metadata Form;
:Set Up Workflow Recipients;
:Choose Action;

if (Action Selected) then (Submit)
  |System|
  :Validate Form Data;
  
  if (Form Valid?) then (No)
    |User|
    :Correct Form Errors;
  else (Yes)
    |System|
    :Process Document Submission;
    |Database|
    :Store Document Metadata;
    :Create Workflow Records;
    :Log Document History;
    :Update Document Status;
    :Record Timestamps;
    |System|
    :Display Success Message;
    |User|
    :View Confirmation;
  endif
  
elseif (Action Selected) then (Save Draft)
  |System|
  :Process Draft Save;
  |Database|
  :Store Draft Document;
  :Save Draft Metadata;
  :Create Draft Entry;
  :Update User's Draft Count;
  |System|
  :Show Draft Saved Message;
  
else (Discard)
  |System|
  :Confirm Discard Action;
  |User|
  :Confirm Discard;
  |Database|
  :Log Discarded Document;
  |System|
  :Redirect to Dashboard;
endif

|Database|
:Complete Transaction;

stop
@enduml 