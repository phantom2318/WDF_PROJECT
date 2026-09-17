function toggleMenu() 
{
    var menu = document.getElementById("menu-options");
    if (menu.style.display === "none") 
        {
        menu.style.display = "block";
        } 
    else 
        {
        menu.style.display = "none";
        }
}

// Define the API URL
const apiUrl = 'https://home.openweathermap.org/api_keys';

// Make a GET request
fetch(apiUrl)
  .then(response => {
    if (!response.ok) {
      throw new Error('Network response was not ok');
    }
    return response.json();
  })
  .then(data => {
    console.log(data);
  })
  .catch(error => {
    console.error('Error:', error);
  });
