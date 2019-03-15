export default function env() { return process.env.NODE_ENV }
const isProduction = () => env() === 'production'
const isDevelopment = () => env() === 'development'

export {
  isProduction,
  isDevelopment,
}