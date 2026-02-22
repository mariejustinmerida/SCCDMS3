@startuml
skinparam dpi 300
skinparam handwritten false
skinparam monochrome false
skinparam packageStyle rectangle
skinparam defaultFontName Arial
skinparam defaultFontSize 12

|#LightBlue|User|
|#LightGreen|System|
|#LightYellow|Google Services|
|#LightPink|Database|

|User|
start
:Access Compose Page;

|System|
:Check Authentication Status;

if (User Logged In?) then (No)
  |User|
  :Login with Credentials;
  |System|
  :Verify Credentials;
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
else (Yes)
endif

|User|
:Select Document Creation Method;

if (Method Selected) then (AI Generator)
  |User|
  :Enter AI Document Prompt;
  |System|
  :Process AI Request;
  |Google Services|
  :Generate Document Content;
elseif (Method Selected) then (Template)
  |User|
  :Select Document Template;
  |System|
  :Process Template Selection;
  |Google Services|
  :Apply Selected Template;
else (Manual)
  |Google Services|
  :Create Blank Document;
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
  |System|
  :Show Draft Saved Message;
  
else (Discard)
  |System|
  :Confirm Discard Action;
  |User|
  :Confirm Discard;
  |System|
  :Redirect to Dashboard;
endif

stop
@enduml 