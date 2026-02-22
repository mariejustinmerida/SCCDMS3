# QR Code E-Signature System for SCCDMS2

## Overview

This QR code e-signature system enhances document verification in the SCC Document Management System by adding a secure, scannable QR code to approved documents. When scanned, the QR code provides verification of document authenticity, approval status, and workflow history.

## Features

- **Secure QR Code Generation**: Creates unique QR codes for each document approval
- **Google Docs Integration**: Automatically inserts QR codes into the top-right corner of Google Docs
- **Verification System**: Provides a public verification page that displays document authenticity and approval history
- **Security Measures**: Implements hash verification and expiration dates for QR codes
- **Automatic Cleanup**: Includes a cron job to remove temporary QR code files older than 30 days

## Installation

1. The QR code system has been integrated into your existing SCCDMS2 system
2. Run the following commands to set up the required database tables and dependencies:

```bash
# Install the QR code library via Composer
cd /path/to/SCCDMS2
composer update

# Create the signatures table
php api/setup_signatures_table.php

# Add QR signature column to documents table
php api/add_qr_signature_column.php
```

## Usage

### Approving Documents with QR Signatures

1. Navigate to your Inbox in the SCCDMS2 dashboard
2. For documents awaiting your approval, click the "QR Approve" button
3. On the approval page, add any comments (optional) and click "Approve with QR Signature"
4. The system will:
   - Process the document approval
   - Generate a unique QR code
   - Insert the QR code into the Google Doc
   - Display the QR code and verification link

### Verifying Documents

1. Scan the QR code on the document using any QR code scanner
2. The scanner will open the verification page showing:
   - Document information (title, type, creator)
   - Signature details (who signed, when, expiration)
   - Complete approval workflow history

## Maintenance

### Cleaning Up Temporary QR Codes

Set up a cron job to run the cleanup script regularly:

```bash
# Run daily at midnight
0 0 * * * php /path/to/SCCDMS2/cron/cleanup_temp_qrcodes.php
```

## Security Considerations

- QR codes include a verification hash to prevent tampering
- Signatures expire after one year (configurable in the code)
- Revoked signatures are marked as invalid in the database

## Troubleshooting

- If QR codes aren't appearing in Google Docs, check your Google API authentication
- Verify that the `temp_qrcodes` directory is writable by the web server
- Check the logs in the `logs` directory for any error messages

## Database Structure

The system adds the following to your database:

1. **signatures table**: Stores signature information
   - `id`: Unique signature ID
   - `document_id`: Associated document ID
   - `user_id`: User who created the signature
   - `office_id`: Office that approved the document
   - `created_at`: Signature creation date
   - `expires_at`: Signature expiration date
   - `is_revoked`: Flag to mark revoked signatures
   - `verification_hash`: Security hash for verification

2. **documents table modification**: Adds `has_qr_signature` column to track documents with QR signatures
