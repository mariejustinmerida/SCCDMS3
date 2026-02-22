
// Get sidebar elements
const sidebarLinks = document.querySelectorAll('.sidebar a');
const mainContent = document.querySelector('main');

// Content mapping
const contentMap = {
  'dashboard': 'index.html',
  'compose': 'compose.php',
  'documents': 'documents.php',
  'incoming': 'incoming.php',
  'outgoing': 'outgoing.php',
  'received': 'received.php',
  'approved': 'approved.php'
};

// Load content function
async function loadContent(page) {
  try {
    const response = await fetch(page);
    const content = await response.text();
    // Extract the main content from the response
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = content;
    const newContent = tempDiv.querySelector('main').innerHTML;
    mainContent.innerHTML = newContent;
  } catch (error) {
    console.error('Error loading content:', error);
  }
}

// Add click event listeners to sidebar links
sidebarLinks.forEach(link => {
  link.addEventListener('click', (e) => {
    e.preventDefault();
    const page = link.getAttribute('data-page');
    if (page && contentMap[page]) {
      loadContent(contentMap[page]);
      // Update active state
      sidebarLinks.forEach(l => l.classList.remove('bg-white/10'));
      link.classList.add('bg-white/10');
    }
  });
});
