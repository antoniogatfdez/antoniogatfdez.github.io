setTimeout(() => {
    const loader = document.querySelector('.loader');
    loader.style.borderTop = '16px solid white';
    loader.style.borderBottom = '16px solid gray';
  
    setTimeout(() => {
      window.location.href = 'intranet.php';
    }, 750); // Cambiar el tiempo según tus necesidades
  }, 1000); // Cambiar el tiempo según tus necesidades 