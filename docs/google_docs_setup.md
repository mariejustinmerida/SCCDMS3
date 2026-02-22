# Google Docs Integration Setup Guide

## Step 1: Create a Google Cloud Project

1. Go to the [Google Cloud Console](https://console.cloud.google.com/)
2. Click on "Select a project" at the top of the page, then click "New Project"
3. Name your project (e.g., "SCCDMS Document System") and click "Create"
4. Make note of your Project ID as you'll need it later

## Step 2: Enable Required APIs

1. In your Google Cloud Console, navigate to "APIs & Services" > "Library"
2. Search for and enable the following APIs:
   - Google Drive API
   - Google Docs API
   - Google Picker API

## Step 3: Create OAuth Credentials

1. Go to "APIs & Services" > "Credentials"
2. Click "Create Credentials" and select "OAuth client ID"
3. If prompted, configure the OAuth consent screen:
   - User Type: External (or Internal if you have Google Workspace)
   - App name: "SCCDMS Document System"
   - User support email: Your email
   - Developer contact information: Your email
   - Authorized domains: Add your domain
4. For the OAuth client ID:
   - Application type: Web application
   - Name: "SCCDMS Web Client"
   - Authorized JavaScript origins: Add your domain (e.g., http://localhost, https://yourdomain.com)
   - Authorized redirect URIs: Add your redirect URI (e.g., http://localhost/SCCDMS/google_auth_callback.php)
5. Click "Create"
6. Download the JSON file with your credentials and save it securely
