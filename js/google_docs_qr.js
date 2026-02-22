/**
 * Google Docs QR Code Integration
 * 
 * This script helps users insert QR codes into Google Docs documents
 * at the top right corner of the document.
 */

// Function to create a QR code image that can be inserted into Google Docs
function createQRCodeForGoogleDocs(verificationUrl, documentId, verificationCode) {
  // Create a container for the QR code
  const container = document.createElement('div');
  container.className = 'google-docs-qr-container';
  container.style.position = 'relative';
  container.style.width = '150px';
  container.style.height = '190px';
  container.style.backgroundColor = '#ffffff';
  container.style.border = '1px solid #e0e0e0';
  container.style.borderRadius = '4px';
  container.style.padding = '10px';
  container.style.boxShadow = '0 2px 4px rgba(0,0,0,0.1)';
  container.style.fontFamily = 'Arial, sans-serif';
  container.style.fontSize = '12px';
  container.style.color = '#333';
  container.style.textAlign = 'center';
  
  // Create the QR code image
  const qrImg = document.createElement('img');
  qrImg.src = `../qr_display.php?url=${encodeURIComponent(verificationUrl)}&size=120`;
  qrImg.alt = 'Document Verification QR Code';
  qrImg.style.width = '120px';
  qrImg.style.height = '120px';
  qrImg.style.marginBottom = '5px';
  
  // Create verification code text
  const codeText = document.createElement('div');
  codeText.textContent = `Verification: ${verificationCode}`;
  codeText.style.fontWeight = 'bold';
  codeText.style.marginBottom = '3px';
  
  // Create document ID text
  const idText = document.createElement('div');
  idText.textContent = `Document ID: ${documentId}`;
  idText.style.fontSize = '10px';
  idText.style.color = '#666';
  
  // Assemble the container
  container.appendChild(qrImg);
  container.appendChild(codeText);
  container.appendChild(idText);
  
  return container;
}

// Function to generate a Google Docs compatible QR code image
function generateGoogleDocsQR(verificationUrl, documentId, verificationCode) {
  // Create an HTML representation of the QR code
  const qrHtml = createQRCodeForGoogleDocs(verificationUrl, documentId, verificationCode);
  
  // Convert to a data URL (this would normally be done with html2canvas in a browser)
  // For our purposes, we'll return the URL to our QR display script
  return `../qr_display.php?url=${encodeURIComponent(verificationUrl)}&size=300&gdocs=1`;
}

// Function to copy the QR code to clipboard for Google Docs
function copyQRForGoogleDocs(verificationUrl, documentId, verificationCode) {
  const imageUrl = generateGoogleDocsQR(verificationUrl, documentId, verificationCode);
  
  // In a real implementation, this would use the Clipboard API
  // For our purposes, we'll provide instructions to the user
  alert('QR Code ready for Google Docs! Right-click the QR code image, select "Copy image", then paste it into your Google Doc.');
  
  return imageUrl;
}
