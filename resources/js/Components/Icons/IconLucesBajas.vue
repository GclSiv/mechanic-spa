<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        width="100%"
        height="100%"
        viewBox="0 0 360 300"
        preserveAspectRatio="xMidYMid meet"
    >
        <g fill="currentColor">
            <!-- Domo del faro -->
            <path d="M 152 62 C 92 62 48 102 48 152 C 48 202 92 242 152 242 L 172 242 L 172 62 Z" />
            <!-- Haces de luz inclinados -->
            <rect x="196" y="76" width="116" height="18" rx="9" transform="rotate(-12 196 76)" />
            <rect x="196" y="116" width="116" height="18" rx="9" transform="rotate(-4 196 116)" />
            <rect x="196" y="156" width="116" height="18" rx="9" transform="rotate(4 196 156)" />
            <rect x="196" y="196" width="116" height="18" rx="9" transform="rotate(12 196 196)" />
        </g>
        <g fill="white">
            <!-- Reflejo interno del faro -->
            <path d="M 148 86 C 106 86 74 114 74 152 C 74 190 106 218 148 218 Z" />
        </g>
    </svg>
</template>
