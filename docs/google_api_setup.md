# Setting Up Google API Access for Document Extraction

This guide explains how to set up Google API access to enable automatic extraction of content from Google Docs, especially for private or access-restricted documents.

## Why API Access is Needed

When documents are private or require authentication, the grammar checker can't automatically extract their content. Using the Google Drive API solves this problem by allowing server-side access with proper authentication.

## Setup Steps

### 1. Create a Google Cloud Project

1. Go to the [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select an existing one
3. Note your Project ID for later use

### 2. Enable the Google Drive API

1. In your Google Cloud project, go to "APIs & Services" > "Library"
2. Search for "Google Drive API" and select it
3. Click "Enable" to activate the API for your project

### 3. Create a Service Account

1. Go to "IAM & Admin" > "Service Accounts"
2. Click "Create Service Account"
3. Enter a name and description for your service account
4. Click "Create and Continue"
5. For the role, select "Basic" > "Editor" (or a more restrictive role if preferred)
6. Click "Continue" and then "Done"

### 4. Generate a Service Account Key

1. From the Service Accounts list, click on your newly created service account
2. Go to the "Keys" tab
3. Click "Add Key" > "Create new key"
4. Select "JSON" as the key type
5. Click "Create" to download the key file

### 5. Configure the System

1. Rename the downloaded JSON key file to `google_service_account.json`
2. Create the directory `storage/` in your SCCDMS2 installation if it doesn't exist
3. Upload the key file to the `storage/` directory

### 6. Share Documents with the Service Account

For private Google Docs that you want to analyze:

1. Open the Google Doc in your browser
2. Click the "Share" button
3. Add the service account email (found in the JSON file under `client_email`) as a viewer
4. Save the sharing settings

## Testing the Setup

After completing the setup:

1. Try the grammar checker on a private Google Doc
2. If automatic extraction fails initially, click the "Try with API" button
3. The system should now be able to extract the content using the API

## Troubleshooting

If you encounter issues:

1. Verify that the service account key file is correctly placed in the `storage/` directory
2. Check that the Google Drive API is enabled for your project
3. Ensure the document is shared with the service account email
4. Check the server logs for any API-related errors

## Security Considerations

The service account key provides access to your Google Drive resources. To maintain security:

1. Use a dedicated Google Cloud project for this purpose only
2. Apply the principle of least privilege when assigning roles to the service account
3. Only share specific documents with the service account, not entire folders
4. Regularly rotate the service account key (create a new one and delete the old one)
5. Monitor API usage in the Google Cloud Console 