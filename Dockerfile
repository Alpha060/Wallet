# Use Node.js 18 Alpine image
FROM node:18-alpine

# Set working directory
WORKDIR /app

# Copy package files
COPY backend/package*.json ./backend/

# Install dependencies
RUN cd backend && npm install --only=production

# Copy application code
COPY . .

# Expose port (Railway uses PORT environment variable)
EXPOSE $PORT

# Set environment variables
ENV NODE_ENV=production

# Start the application
CMD ["node", "backend/server/server.js"]