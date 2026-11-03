const titles = {
    'home': 'Home Dashboard',
    'payments': 'Payments & Billing',
    'forum': 'Community Forum',
    'profile': 'Resident Profile'
};

function switchPage(pageId, event) {
    // Prevent the default anchor href="#" jump
    event.preventDefault();

    // 1. Hide all page views
    const pages = document.querySelectorAll('.page-view');
    pages.forEach(page => page.classList.add('hidden'));

    // 2. Show the selected page view
    document.getElementById('page-' + pageId).classList.remove('hidden');

    // 3. Update the topbar header title
    document.getElementById('page-title').textContent = titles[pageId];

    // 4. Update active class styling on the sidebar links
    const navLinks = document.querySelectorAll('.sidebar-nav a');
    navLinks.forEach(link => link.classList.remove('active'));
    
    document.getElementById('nav-' + pageId).classList.add('active');
}