document.addEventListener("DOMContentLoaded", () => {
    // Fixed header behavior
    const header = document.querySelector(".navbar")
  
    window.addEventListener("scroll", () => {
      if (window.scrollY > 50) {
        header.style.padding = "0.5rem 0"
        header.style.backgroundColor = "rgba(10, 10, 32, 0.95)"
      } else {
        header.style.padding = "1rem 0"
        header.style.backgroundColor = "rgba(10, 10, 32, 0.9)"
      }
    })
  
    // Event card slider functionality
    const prevBtn = document.querySelector(".nav-prev")
    const nextBtn = document.querySelector(".nav-next")
    const eventCards = document.querySelector(".event-cards")
  
    if (prevBtn && nextBtn && eventCards) {
      let currentSlide = 0
      const totalSlides = document.querySelectorAll(".event-card").length
      const maxSlides = Math.ceil(totalSlides / 3)
  
      prevBtn.addEventListener("click", () => {
        if (currentSlide > 0) {
          currentSlide--
          updateSliderPosition()
        }
      })
  
      nextBtn.addEventListener("click", () => {
        if (currentSlide < maxSlides - 1) {
          currentSlide++
          updateSliderPosition()
        }
      })
  
      function updateSliderPosition() {
        // Only slide on mobile
        if (window.innerWidth < 768) {
          const slideWidth = document.querySelector(".event-card").offsetWidth + 16 // card width + margin
          eventCards.style.transform = `translateX(-${currentSlide * slideWidth}px)`
        } else {
          eventCards.style.transform = "translateX(0)"
        }
      }
  
      // Reset on window resize
      window.addEventListener("resize", () => {
        currentSlide = 0
        updateSliderPosition()
      })
    }
  
    // Duplicate the trending items for continuous scrolling
    const trendingTrack = document.querySelector(".trending-track")
    if (trendingTrack) {
      const trendingItems = document.querySelectorAll(".trending-item")
      const clonedItems = Array.from(trendingItems).map((item) => item.cloneNode(true))
  
      clonedItems.forEach((item) => {
        trendingTrack.appendChild(item)
      })
    }
  })
  
  