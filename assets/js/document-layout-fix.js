/**
 * Document Management System Layout Fix Script
 * 
 * This script addresses layout issues with document folders and cards by:
 * 1. Ensuring folders display properly
 * 2. Forcing document cards to render with correct styling
 * 3. Adding event listeners to fix folder toggling
 */

// Function to force open a specific folder (compatible with search/sort)
function forceOpenFolder(folderId) {
    console.log('Layout fix: Forcing open folder:', folderId);
    
    // Get folder elements
    const folderContent = document.querySelector(`.folder-content[data-folder="${folderId}"]`);
    const folderHeader = document.querySelector(`.folder-header[data-folder="${folderId}"]`);
    
    // First force all folders closed
    const allFolderContents = document.querySelectorAll('.folder-content');
    allFolderContents.forEach(content => {
        if (content.getAttribute('data-folder') !== folderId) {
            content.style.display = 'none';
            content.classList.remove('open');
        }
    });
    
    // If folder elements exist, force them open with inline styles
    if (folderContent && folderHeader) {
        // Add open class
        folderContent.classList.add('open');
        folderHeader.classList.add('open');
        
        // Apply direct styling
        folderContent.style.display = 'grid';
        folderContent.style.gridTemplateColumns = 'repeat(auto-fill, minmax(250px, 1fr))';
        folderContent.style.gap = '1rem';
        folderContent.style.visibility = 'visible';
        
        // Rotate the folder toggle icon
        const icon = folderHeader.querySelector('.folder-toggle-icon');
        if (icon) {
            icon.style.transition = 'transform 0.3s ease';
            icon.style.transform = 'rotate(180deg)';
        }
        
        // Log successful open
        console.log('Successfully opened folder:', folderId);
    } else {
        console.error('Could not find folder elements for:', folderId);
    }
    
    // Force document cards to display correctly
    fixDocumentCards();
    
    // Update URL with the selected folder
    const url = new URL(window.location);
    url.searchParams.set('folder', folderId);
    window.history.replaceState({}, '', url);
}

// Function to fix all document cards
function fixDocumentCards() {
    const documentCards = document.querySelectorAll('.document-card');
    
    documentCards.forEach(card => {
        // Apply direct styling
        card.style.display = 'flex';
        card.style.flexDirection = 'column';
        card.style.margin = '0.5rem 0';
        card.style.borderRadius = '0.5rem';
        card.style.backgroundColor = 'white';
        card.style.boxShadow = '0 1px 3px rgba(0,0,0,0.1)';
        card.style.border = '1px solid #e5e7eb';
        card.style.overflow = 'hidden';
        
        // Fix card children
        const header = card.querySelector('.document-card-header');
        if (header) {
            header.style.display = 'flex';
            header.style.alignItems = 'center';
            header.style.padding = '0.75rem';
            header.style.borderBottom = '1px solid #e5e7eb';
        }
        
        const body = card.querySelector('.document-card-body');
        if (body) {
            body.style.flex = '1';
            body.style.padding = '0.75rem';
        }
        
        const footer = card.querySelector('.document-card-footer');
        if (footer) {
            footer.style.display = 'flex';
            footer.style.justifyContent = 'space-between';
            footer.style.padding = '0.75rem';
            footer.style.borderTop = '1px solid #e5e7eb';
            footer.style.backgroundColor = '#f9fafb';
        }
        
        // Ensure document actions are properly styled
        const actions = card.querySelector('.document-actions');
        if (actions) {
            actions.style.display = 'flex';
            actions.style.flexWrap = 'wrap';
            actions.style.gap = '8px';
        }
    });
}

// Function to force container visibility
function forceDocumentsContainerVisible() {
    const container = document.getElementById('documents-container');
    if (container) {
        container.style.display = 'block';
        container.style.visibility = 'visible';
    }
}

// Function to ensure folder headers are clickable
function makeFolderHeadersClickable() {
    const folderHeaders = document.querySelectorAll('.folder-header');
    
    folderHeaders.forEach(header => {
        const folderId = header.getAttribute('data-folder');
        
        // Remove existing event listeners by cloning and replacing
        const newHeader = header.cloneNode(true);
        header.parentNode.replaceChild(newHeader, header);
        
        // Add new click event listener
        newHeader.addEventListener('click', function(e) {
            e.preventDefault();
            forceOpenFolder(folderId);
        });
        
        // Add inline onclick attribute as backup
        newHeader.setAttribute('onclick', `forceOpenFolder('${folderId}')`);
    });
}

// Main function to fix document layout
function fixDocumentLayout() {
    console.log('Layout fix: Fixing document layout...');
    
    // Don't interfere if search/sort functions are active
    const searchInput = document.getElementById('document-search');
    if (searchInput && searchInput.value.trim() !== '') {
        console.log('Layout fix: Skipping - search is active');
        return;
    }
    
    // Force container visibility
    forceDocumentsContainerVisible();
    
    // Make folder headers clickable
    makeFolderHeadersClickable();
    
    // Fix document cards
    fixDocumentCards();
    
    // Get the current folder from URL or default to 'all'
    const urlParams = new URLSearchParams(window.location.search);
    const folder = urlParams.get('folder') || 'all';
    
    // Force open the current folder
    forceOpenFolder(folder);
    
    console.log('Document layout fix completed');
}

// Run the fix when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    fixDocumentLayout();
    
    // Run again after a short delay to ensure everything is loaded
    setTimeout(fixDocumentLayout, 500);
});

// Also run the fix after window loads to catch any dynamically loaded content
window.addEventListener('load', function() {
    fixDocumentLayout();
});

// Run again periodically to ensure everything stays visible (less frequent to avoid conflicts)
setInterval(function() {
    // Re-fix only if a folder is not open and no search is active
    const openFolders = document.querySelectorAll('.folder-content.open');
    const searchInput = document.getElementById('document-search');
    const isSearchActive = searchInput && searchInput.value.trim() !== '';
    
    if (openFolders.length === 0 && !isSearchActive) {
        fixDocumentLayout();
    }
}, 5000); // Increased interval to 5 seconds 