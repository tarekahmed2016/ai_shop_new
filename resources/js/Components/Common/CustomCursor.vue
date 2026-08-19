<script setup>
import { onMounted, onBeforeUnmount } from 'vue'

let handleMouseMove, handleMouseEnter, handleMouseLeave

onMounted(() => {
  // Disable on mobile/touch devices
  const isMobile = 'ontouchstart' in window || navigator.maxTouchPoints > 0 || window.innerWidth < 768
  if (isMobile) return

  const mouseInner = document.querySelector('.mouse-inner')
  const mouseOuter = document.querySelector('.mouse-outer')

  if (!mouseInner || !mouseOuter) return

  let clientX = 0
  let clientY = 0

  handleMouseMove = (event) => {
    clientX = event.clientX
    clientY = event.clientY

    mouseInner.style.transform = `translate(${clientX}px, ${clientY}px)`
    mouseOuter.style.transform = `translate(${clientX}px, ${clientY}px)`
  }

  handleMouseEnter = (event) => {
    const target = event.target
    if (target.tagName === 'A' || target.classList.contains('cursor-pointer') || target.closest('a, .cursor-pointer')) {
      mouseInner.classList.add('mouse-hover')
      mouseOuter.classList.add('mouse-hover')
    }
  }

  handleMouseLeave = (event) => {
    const target = event.target
    if (target.tagName === 'A' || target.classList.contains('cursor-pointer')) {
      if (!target.closest('.cursor-pointer') || target.classList.contains('cursor-pointer')) {
        mouseInner.classList.remove('mouse-hover')
        mouseOuter.classList.remove('mouse-hover')
      }
    }
  }

  // Add event listeners
  window.addEventListener('mousemove', handleMouseMove)
  document.body.addEventListener('mouseenter', handleMouseEnter, true)
  document.body.addEventListener('mouseleave', handleMouseLeave, true)

  // Make cursors visible
  mouseInner.style.visibility = 'visible'
  mouseOuter.style.visibility = 'visible'
})

// Cleanup at top level (not inside onMounted)
onBeforeUnmount(() => {
  if (handleMouseMove) {
    window.removeEventListener('mousemove', handleMouseMove)
  }
  if (handleMouseEnter) {
    document.body.removeEventListener('mouseenter', handleMouseEnter, true)
  }
  if (handleMouseLeave) {
    document.body.removeEventListener('mouseleave', handleMouseLeave, true)
  }
})
</script>

<template>
  <div>
    <div class="mouse-move mouse-outer"></div>
    <div class="mouse-move mouse-inner"></div>
  </div>
</template>

<style>
/* Mouse Cursor Css */
.mouse-move {
  position: fixed;
  left: 0;
  top: 0;
  pointer-events: none;
  border-radius: 50%;
  -webkit-transform: translateZ(0);
  transform: translateZ(0);
  visibility: hidden;
}

.mouse-inner{
  z-index: 10000001;
}

.mouse-inner, .mouse-inner.mouse-hover {
  margin-left: -25px;
  margin-top: -25px;
  width: 50px;
  height: 50px;
  background-color: #6176f6;
  opacity: 0.1;
}

/* Dark mode support */
.dark .mouse-outer {
  border-color: #818cf8;
}

.dark .mouse-inner {
  background-color: #818cf8;
}
</style>
